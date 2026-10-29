<?php
// admin/users.php - UI-UX Pro Max Overhaul
require_once __DIR__ . '/../config/db.php';

// Proteksi login admin
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: login.php');
    exit;
}

// Proteksi RBAC khusus superadmin
$current_admin_role = $_SESSION['admin_role'] ?? 'panitia';
if ($current_admin_role !== 'superadmin') {
    die("Akses Ditolak. Halaman ini hanya dapat diakses oleh Super Admin.");
}

$current_user_id = (int)($_SESSION['admin_id'] ?? 0);
$success_msg = '';
$error_msg = '';

$allowed_roles = ['superadmin', 'panitia', 'bendahara', 'humas', 'yayasan'];

// Helper metadata styling & label role
if (!function_exists('get_role_badge_meta')) {
    function get_role_badge_meta($role) {
        switch ((string)$role) {
            case 'superadmin':
                return [
                    'label' => 'Super Admin',
                    'class' => 'badge-superadmin',
                    'bg_avatar' => 'avatar-superadmin',
                    'avatar_style' => 'background: linear-gradient(135deg, #E11D48 0%, #BE123C 100%) !important; color: #FFFFFF !important;',
                    'badge_style' => 'background-color: #FFF1F2 !important; color: #9F1239 !important; border: 1px solid #FECDD3 !important;',
                    'icon' => 'fa-solid fa-shield-halved',
                    'desc' => 'Akses penuh seluruh sistem, pengaturan email & pengguna'
                ];
            case 'bendahara':
                return [
                    'label' => 'Bendahara Keuangan',
                    'class' => 'badge-bendahara',
                    'bg_avatar' => 'avatar-bendahara',
                    'avatar_style' => 'background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%) !important; color: #FFFFFF !important;',
                    'badge_style' => 'background-color: #EFF6FF !important; color: #1E40AF !important; border: 1px solid #BFDBFE !important;',
                    'icon' => 'fa-solid fa-file-invoice-dollar',
                    'desc' => 'Master biaya pendaftaran, validasi bayar & laporan keuangan'
                ];
            case 'humas':
                return [
                    'label' => 'Humas & Konten',
                    'class' => 'badge-humas',
                    'bg_avatar' => 'avatar-humas',
                    'avatar_style' => 'background: linear-gradient(135deg, #D97706 0%, #B45309 100%) !important; color: #FFFFFF !important;',
                    'badge_style' => 'background-color: #FFFBEB !important; color: #92400E !important; border: 1px solid #FDE68A !important;',
                    'icon' => 'fa-solid fa-bullhorn',
                    'desc' => 'Kelola berita, sarana fasilitas, ekskul, dan galeri prestasi'
                ];
            case 'yayasan':
                return [
                    'label' => 'Pimpinan Yayasan',
                    'class' => 'badge-yayasan',
                    'bg_avatar' => 'avatar-yayasan',
                    'avatar_style' => 'background: linear-gradient(135deg, #7C3AED 0%, #6D28D9 100%) !important; color: #FFFFFF !important;',
                    'badge_style' => 'background-color: #F5F3FF !important; color: #5B21B6 !important; border: 1px solid #DDD6FE !important;',
                    'icon' => 'fa-solid fa-landmark',
                    'desc' => 'Monitoring eksekutif read-only rekap santri & keuangan'
                ];
            default: // panitia
                return [
                    'label' => 'Panitia Admisi',
                    'class' => 'badge-panitia',
                    'bg_avatar' => 'avatar-panitia',
                    'avatar_style' => 'background: linear-gradient(135deg, #059669 0%, #047857 100%) !important; color: #FFFFFF !important;',
                    'badge_style' => 'background-color: #ECFDF5 !important; color: #065F46 !important; border: 1px solid #A7F3D0 !important;',
                    'icon' => 'fa-solid fa-users',
                    'desc' => 'Input santri baru, kelola pendaftar, dan scan QR kartu ujian'
                ];
        }
    }
}

// =========================================================================
// 1. TANGANI AKSI CRUD PENGGUNA (TAMBAH, EDIT, HAPUS)
// =========================================================================
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'] ?? '';
    
    // Aksi 1: Tambah Pengguna Baru
    if ($action === 'add') {
        $username = strtolower(trim(sanitize($_POST['username'] ?? '')));
        $nama_lengkap = trim(sanitize($_POST['nama_lengkap'] ?? ''));
        $role = trim(sanitize($_POST['role'] ?? 'panitia'));
        $password = $_POST['password'] ?? '';
        
        if (!in_array($role, $allowed_roles)) {
            $role = 'panitia';
        }
        
        if (empty($username) || empty($password) || empty($nama_lengkap)) {
            $error_msg = "Seluruh field wajib diisi lengkap untuk menambah akun pengguna baru.";
        } elseif (strlen($password) < 6) {
            $error_msg = "Password minimal harus 6 karakter demi keamanan akun.";
        } else {
            try {
                // Cek duplikasi username
                $s_cek = $db->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
                $s_cek->execute(['username' => $username]);
                if ($s_cek->fetch()) {
                    $error_msg = "Username \"{$username}\" sudah digunakan oleh pengguna lain. Silakan pilih username lain.";
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO users (username, password, nama_lengkap, role) VALUES (:username, :password, :nama_lengkap, :role)");
                    $stmt->execute([
                        'username' => $username,
                        'password' => $hash,
                        'nama_lengkap' => $nama_lengkap,
                        'role' => $role
                    ]);
                    $success_msg = "Akun pengguna baru \"{$nama_lengkap}\" (@{$username}) berhasil ditambahkan!";
                }
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $error_msg = "Username sudah terdaftar dalam sistem.";
                } else {
                    $error_msg = "Gagal menambah akun: " . $e->getMessage();
                }
            }
        }
    }
    
    // Aksi 2: Edit Akun Pengguna
    elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $username = strtolower(trim(sanitize($_POST['username'] ?? '')));
        $nama_lengkap = trim(sanitize($_POST['nama_lengkap'] ?? ''));
        $role = trim(sanitize($_POST['role'] ?? 'panitia'));
        $password = $_POST['password'] ?? '';
        
        if (!in_array($role, $allowed_roles)) {
            $role = 'panitia';
        }
        
        if ($id <= 0 || empty($username) || empty($nama_lengkap)) {
            $error_msg = "ID, Username, dan Nama Lengkap wajib diisi.";
        } else {
            try {
                // Cek duplikasi username untuk ID lain
                $s_cek = $db->prepare("SELECT id FROM users WHERE username = :username AND id != :id LIMIT 1");
                $s_cek->execute(['username' => $username, 'id' => $id]);
                if ($s_cek->fetch()) {
                    $error_msg = "Username \"{$username}\" sudah digunakan oleh pengguna lain.";
                } else {
                    if (!empty($password)) {
                        if (strlen($password) < 6) {
                            $error_msg = "Password baru minimal harus 6 karakter.";
                        } else {
                            $hash = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $db->prepare("UPDATE users SET username = :username, nama_lengkap = :nama_lengkap, role = :role, password = :password WHERE id = :id");
                            $stmt->execute([
                                'username' => $username,
                                'nama_lengkap' => $nama_lengkap,
                                'role' => $role,
                                'password' => $hash,
                                'id' => $id
                            ]);
                            $success_msg = "Data akun pengguna dan password berhasil diperbarui!";
                        }
                    } else {
                        $stmt = $db->prepare("UPDATE users SET username = :username, nama_lengkap = :nama_lengkap, role = :role WHERE id = :id");
                        $stmt->execute([
                            'username' => $username,
                            'nama_lengkap' => $nama_lengkap,
                            'role' => $role,
                            'id' => $id
                        ]);
                        $success_msg = "Data akun pengguna berhasil diperbarui!";
                    }
                    
                    // Jika mengupdate akun diri sendiri, perbarui session saat ini
                    if (empty($error_msg) && $id === $current_user_id) {
                        $_SESSION['admin_nama'] = $nama_lengkap;
                        $_SESSION['admin_username'] = $username;
                        $_SESSION['admin_role'] = $role;
                    }
                }
            } catch (PDOException $e) {
                $error_msg = "Gagal memperbarui akun: " . $e->getMessage();
            }
        }
    }
    
    // Aksi 3: Hapus Pengguna
    elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === $current_user_id) {
            $error_msg = "Anda tidak dapat menghapus akun Anda sendiri saat sedang aktif login di sistem!";
        } elseif ($id <= 0) {
            $error_msg = "ID pengguna tidak valid.";
        } else {
            try {
                // Ambil info nama untuk notifikasi
                $s_u = $db->prepare("SELECT nama_lengkap, username FROM users WHERE id = :id");
                $s_u->execute(['id' => $id]);
                $u_info = $s_u->fetch();
                
                $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
                $stmt->execute(['id' => $id]);
                
                $del_name = $u_info ? $u_info['nama_lengkap'] : "ID #$id";
                $success_msg = "Akun pengguna \"{$del_name}\" berhasil dihapus dari sistem.";
            } catch (PDOException $e) {
                $error_msg = "Gagal menghapus pengguna: " . $e->getMessage();
            }
        }
    }
}

// =========================================================================
// 2. AMBIL DAFTAR SEMUA PENGGUNA & ANALISIS METRIK KPI
// =========================================================================
try {
    $stmt_all = $db->query("SELECT * FROM users ORDER BY id ASC");
    $users = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
}

$total_users = count($users);
$role_counts = [
    'superadmin' => 0,
    'panitia' => 0,
    'bendahara' => 0,
    'humas' => 0,
    'yayasan' => 0
];

foreach ($users as $u) {
    $r = $u['role'] ?? 'panitia';
    if (isset($role_counts[$r])) {
        $role_counts[$r]++;
    } else {
        $role_counts['panitia']++;
    }
}

$page_title = 'Manajemen Pengguna (Admin) - Admin Babul Jannah';
include __DIR__ . '/includes/head.php';
?>

<style>
    /* Brand Color Fallbacks */
    .bg-brand-primary { background-color: #5C3E35 !important; }
    .bg-brand-deep { background-color: #432B25 !important; }
    .text-brand-primary { color: #5C3E35 !important; }
    .border-brand-primary { border-color: #5C3E35 !important; }

    /* Custom line clamp */
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
</style>

<body class="bg-gray-100 flex h-screen overflow-hidden text-[#2F2522] font-sans antialiased">

    <!-- Desktop Sidebar Navigation -->
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- Main Content Wrapper -->
    <div class="flex-grow flex flex-col h-full overflow-hidden min-w-0">
        
        <!-- Header Navbar -->
        <?php include __DIR__ . '/includes/header.php'; ?>

        <!-- Scrollable Main Container (Single Transparent Scrollbar) -->
        <main class="flex-grow p-4 sm:p-5 lg:p-6 overflow-y-auto space-y-5 custom-scrollbar bg-gray-50/70">

            <!-- ======================================================== -->
            <!-- 1. TOP ACTION & RBAC STATUS BAR (UI-UX PRO MAX) -->
            <!-- ======================================================== -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 sm:p-5 rounded-2xl border border-gray-200/80 shadow-xs">
                <div>
                    <div class="flex items-center space-x-2 text-[11px] font-extrabold text-brand-primary uppercase tracking-wider">
                        <i class="fa-solid fa-users-gear"></i>
                        <span>Manajemen Pengguna &amp; Hak Akses (RBAC)</span>
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        <span class="text-emerald-700 normal-case font-bold">Role-Based Access Control</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Kelola akun login staf panitia, bendahara keuangan, tim humas portal, serta pengawasan pimpinan yayasan.
                    </p>
                </div>

                <!-- Add User Button -->
                <button type="button" onclick="openModalTambah()" class="inline-flex items-center space-x-1.5 bg-brand-primary hover:bg-brand-deep text-white font-bold py-2 px-3.5 rounded-xl text-xs shadow-xs hover:shadow transition transform active:scale-95" title="Tambah Pengguna Baru">
                    <i class="fa-solid fa-user-plus text-xs"></i>
                    <span>Tambah Pengguna Baru</span>
                </button>
            </div>

            <!-- Alerts Notification -->
            <?php if ($success_msg): ?>
                <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 p-3.5 rounded-2xl shadow-xs flex items-center justify-between animate-in fade-in duration-200">
                    <div class="flex items-center space-x-2.5">
                        <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
                        <p class="font-bold text-xs"><?= htmlspecialchars($success_msg) ?></p>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800 p-1"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="bg-rose-50 border border-rose-300 text-rose-800 p-3.5 rounded-2xl shadow-xs flex items-center justify-between animate-in fade-in duration-200">
                    <div class="flex items-center space-x-2.5">
                        <i class="fa-solid fa-circle-exclamation text-rose-600 text-base"></i>
                        <p class="font-bold text-xs"><?= htmlspecialchars($error_msg) ?></p>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-rose-600 hover:text-rose-800 p-1"><i class="fa-solid fa-xmark"></i></button>
                </div>
            <?php endif; ?>

            <!-- ======================================================== -->
            <!-- 2. TOP METRIC OVERVIEW CARDS (4 KPI STATS) -->
            <!-- ======================================================== -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                
                <!-- Stat 1: Total Pengguna -->
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-xl bg-brand-primary/10 text-brand-primary border border-brand-primary/20 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Total Pengguna</span>
                        <div class="flex items-baseline space-x-1.5">
                            <span class="text-xl font-black text-gray-900"><?= $total_users ?></span>
                            <span class="text-[10px] text-gray-500 font-semibold">Akun Aktif</span>
                        </div>
                    </div>
                </div>

                <!-- Stat 2: Super Admin -->
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-700 border border-rose-200 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Super Admin</span>
                        <div class="flex items-baseline space-x-1.5">
                            <span class="text-xl font-black text-rose-800"><?= $role_counts['superadmin'] ?></span>
                            <span class="text-[10px] text-rose-700 font-semibold">Akses Penuh</span>
                        </div>
                    </div>
                </div>

                <!-- Stat 3: Panitia PSB -->
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-user-pen"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Panitia Admisi</span>
                        <div class="flex items-baseline space-x-1.5">
                            <span class="text-xl font-black text-emerald-800"><?= $role_counts['panitia'] ?></span>
                            <span class="text-[10px] text-emerald-700 font-semibold">Staf PSB</span>
                        </div>
                    </div>
                </div>

                <!-- Stat 4: Bendahara, Humas & Yayasan -->
                <div class="bg-white p-4 rounded-2xl border border-gray-200/80 shadow-xs flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-700 border border-blue-200 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="fa-solid fa-landmark"></i>
                    </div>
                    <div class="min-w-0">
                        <span class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider">Keuangan &amp; Yayasan</span>
                        <div class="flex items-baseline space-x-1.5">
                            <span class="text-xl font-black text-blue-800"><?= $role_counts['bendahara'] + $role_counts['humas'] + $role_counts['yayasan'] ?></span>
                            <span class="text-[10px] text-blue-700 font-semibold">Staf Khusus</span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ======================================================== -->
            <!-- 3. SMART SEARCH, FILTER ROLE PILLS & VIEW MODE SWITCHER -->
            <!-- ======================================================== -->
            <div class="bg-white p-4 sm:p-5 rounded-2xl border border-gray-200/80 shadow-xs space-y-3.5">
                
                <!-- Row 1: Search & Controls -->
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                    
                    <!-- Search Input -->
                    <div class="relative flex-grow max-w-md">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                            <i class="fa-solid fa-magnifying-glass text-xs"></i>
                        </span>
                        <input type="text" id="search-user" oninput="filterUsers()" placeholder="Cari nama lengkap, username login (@admin)..."
                            class="w-full pl-9 pr-8 py-2 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-800 placeholder-gray-400 focus:outline-none focus:border-brand-primary focus:bg-white focus:ring-1 focus:ring-brand-primary/20 transition font-bold">
                        <button type="button" id="btn-clear-search" onclick="clearSearch()" class="hidden absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                            <i class="fa-solid fa-circle-xmark text-xs"></i>
                        </button>
                    </div>

                    <!-- Right Controls: Sort & View Mode Switcher -->
                    <div class="flex items-center space-x-3 flex-shrink-0">
                        
                        <!-- Sort Filter -->
                        <div class="flex items-center space-x-1.5 text-xs text-gray-600">
                            <label for="sort-user" class="text-[11px] font-bold text-gray-500 hidden sm:inline">Urutkan:</label>
                            <select id="sort-user" onchange="sortUsers()" class="bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-xl px-2.5 py-1.5 focus:outline-none focus:border-brand-primary font-bold">
                                <option value="id_asc">ID Pengguna (Lama - Baru)</option>
                                <option value="id_desc">ID Pengguna (Baru - Lama)</option>
                                <option value="nama_az">Nama (A - Z)</option>
                                <option value="nama_za">Nama (Z - A)</option>
                            </select>
                        </div>

                        <!-- View Switcher (Grid vs Table) -->
                        <div class="flex items-center bg-gray-100 p-1 rounded-xl border border-gray-200/80 text-xs">
                            <button type="button" id="btn-view-grid" onclick="setViewMode('grid')" class="p-1.5 px-2.5 rounded-lg font-bold transition flex items-center space-x-1.5 bg-brand-primary text-white shadow-xs" title="Tampilan Grid Kartu Pengguna">
                                <i class="fa-solid fa-grip"></i>
                                <span class="hidden sm:inline text-[11px]">Grid</span>
                            </button>
                            <button type="button" id="btn-view-table" onclick="setViewMode('table')" class="p-1.5 px-2.5 rounded-lg font-bold transition flex items-center space-x-1.5 text-gray-500 hover:bg-gray-200" title="Tampilan Tabel Data">
                                <i class="fa-solid fa-list"></i>
                                <span class="hidden sm:inline text-[11px]">Tabel</span>
                            </button>
                        </div>

                    </div>
                </div>

                <!-- Row 2: Role Filter Tabs Pills -->
                <div class="flex items-center space-x-1.5 overflow-x-auto custom-scrollbar pb-1 text-xs">
                    <button type="button" onclick="setRoleFilter('all', this)" class="btn-role-tab px-3 py-1.5 rounded-xl font-bold transition whitespace-nowrap bg-brand-primary text-white shadow-xs">
                        Semua Role (<?= $total_users ?>)
                    </button>
                    <button type="button" onclick="setRoleFilter('superadmin', this)" class="btn-role-tab px-3 py-1.5 rounded-xl font-bold transition whitespace-nowrap text-gray-600 hover:bg-gray-100">
                        <i class="fa-solid fa-shield-halved mr-1 text-rose-500"></i> Super Admin (<?= $role_counts['superadmin'] ?>)
                    </button>
                    <button type="button" onclick="setRoleFilter('panitia', this)" class="btn-role-tab px-3 py-1.5 rounded-xl font-bold transition whitespace-nowrap text-gray-600 hover:bg-gray-100">
                        <i class="fa-solid fa-users mr-1 text-emerald-500"></i> Panitia Admisi (<?= $role_counts['panitia'] ?>)
                    </button>
                    <button type="button" onclick="setRoleFilter('bendahara', this)" class="btn-role-tab px-3 py-1.5 rounded-xl font-bold transition whitespace-nowrap text-gray-600 hover:bg-gray-100">
                        <i class="fa-solid fa-file-invoice-dollar mr-1 text-blue-500"></i> Bendahara (<?= $role_counts['bendahara'] ?>)
                    </button>
                    <button type="button" onclick="setRoleFilter('humas', this)" class="btn-role-tab px-3 py-1.5 rounded-xl font-bold transition whitespace-nowrap text-gray-600 hover:bg-gray-100">
                        <i class="fa-solid fa-bullhorn mr-1 text-amber-500"></i> Humas (<?= $role_counts['humas'] ?>)
                    </button>
                    <button type="button" onclick="setRoleFilter('yayasan', this)" class="btn-role-tab px-3 py-1.5 rounded-xl font-bold transition whitespace-nowrap text-gray-600 hover:bg-gray-100">
                        <i class="fa-solid fa-landmark mr-1 text-purple-500"></i> Yayasan (<?= $role_counts['yayasan'] ?>)
                    </button>
                </div>

                <!-- Info Bar: Showing Count -->
                <div class="flex items-center justify-between text-[11px] text-gray-500 pt-1 border-t border-gray-100">
                    <div>
                        Menampilkan <strong id="visible-count" class="text-gray-900"><?= $total_users ?></strong> dari <strong class="text-gray-900"><?= $total_users ?></strong> akun pengguna aktif
                    </div>
                    <div class="flex items-center space-x-1.5 text-[10px] text-gray-400">
                        <i class="fa-solid fa-lock text-[9px]"></i>
                        <span>Password terenkripsi dengan algoritma standar keamanan Bcrypt PHP</span>
                    </div>
                </div>

            </div>

            <!-- ======================================================== -->
            <!-- 4A. GRID VIEW: KARTU PENGGUNA INTERAKTIF (DEFAULT VIEW) -->
            <!-- ======================================================== -->
            <div id="view-grid-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
                <?php foreach ($users as $u): 
                    $role_meta = get_role_badge_meta($u['role'] ?? 'panitia');
                    $is_self = ((int)$u['id'] === $current_user_id);
                    $initials = strtoupper(substr($u['nama_lengkap'] ?? $u['username'], 0, 2));
                ?>
                    <div class="user-item-card bg-white rounded-3xl border <?= $is_self ? 'border-brand-primary ring-1 ring-brand-primary/20 shadow-md' : 'border-gray-200/90 shadow-xs' ?> hover:shadow-lg transition-all duration-300 flex flex-col justify-between overflow-hidden p-5 space-y-4"
                         data-id="<?= $u['id'] ?>"
                         data-nama="<?= htmlspecialchars(strtolower($u['nama_lengkap'])) ?>"
                         data-username="<?= htmlspecialchars(strtolower($u['username'])) ?>"
                         data-role="<?= htmlspecialchars(strtolower($u['role'] ?? 'panitia')) ?>">
                        
                        <!-- Top Card Header: Avatar & Role Pill -->
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center space-x-3.5 min-w-0">
                                <!-- Avatar -->
                                <div class="w-12 h-12 rounded-2xl <?= $role_meta['bg_avatar'] ?> flex items-center justify-center font-black text-sm shadow-xs flex-shrink-0" style="<?= $role_meta['avatar_style'] ?>">
                                    <?= htmlspecialchars($initials) ?>
                                </div>
                                
                                <!-- User Identity -->
                                <div class="min-w-0 space-y-0.5">
                                    <h3 class="font-black text-gray-900 text-sm truncate" title="<?= htmlspecialchars($u['nama_lengkap']) ?>">
                                        <?= htmlspecialchars($u['nama_lengkap']) ?>
                                    </h3>
                                    <div class="flex items-center space-x-1.5">
                                        <span class="text-xs font-mono font-bold text-brand-primary">@<?= htmlspecialchars($u['username']) ?></span>
                                        <?php if ($is_self): ?>
                                            <span class="inline-flex items-center text-[9px] font-extrabold bg-emerald-100 text-emerald-800 px-1.5 py-0.2 rounded-md">
                                                <i class="fa-solid fa-circle text-[6px] text-emerald-500 mr-1 animate-pulse"></i> Anda
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Role Pill Badge -->
                            <span class="inline-flex items-center text-[10px] font-extrabold px-2.5 py-1 rounded-xl border shadow-2xs whitespace-nowrap <?= $role_meta['class'] ?>" style="<?= $role_meta['badge_style'] ?>">
                                <i class="<?= $role_meta['icon'] ?> mr-1.5 text-[9px]"></i>
                                <?= htmlspecialchars($role_meta['label']) ?>
                            </span>
                        </div>

                        <!-- Role Description & Details -->
                        <div class="bg-gray-50/80 p-3 rounded-2xl border border-gray-150 space-y-1.5 text-xs text-gray-600">
                            <div class="flex items-center justify-between text-[11px] text-gray-500">
                                <span class="font-bold">Otoritas Hak Akses:</span>
                                <span>ID: <strong>#<?= $u['id'] ?></strong></span>
                            </div>
                            <p class="text-[11px] text-gray-600 leading-relaxed font-medium">
                                <?= htmlspecialchars($role_meta['desc']) ?>
                            </p>
                            <?php if (!empty($u['created_at'])): ?>
                                <div class="text-[10px] text-gray-400 pt-1 border-t border-gray-200/60 flex items-center space-x-1">
                                    <i class="fa-regular fa-calendar-check text-[9px]"></i>
                                    <span>Terdaftar sejak <?= date('d M Y, H:i', strtotime($u['created_at'])) ?> WIB</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Card Footer Action Buttons -->
                        <div class="flex items-center justify-between gap-2 pt-1 border-t border-gray-100">
                            <span class="text-[10px] text-gray-400 font-medium">
                                <?= $is_self ? '<i class="fa-solid fa-shield text-emerald-500 mr-1"></i> Sesi Aktif' : '<i class="fa-solid fa-circle text-[7px] text-gray-300 mr-1"></i> Siap Bertugas' ?>
                            </span>

                            <div class="flex items-center space-x-1.5">
                                <!-- Edit Button -->
                                <button type="button" 
                                        onclick="openModalEdit(this)"
                                        data-id="<?= $u['id'] ?>"
                                        data-nama="<?= htmlspecialchars($u['nama_lengkap'], ENT_QUOTES) ?>"
                                        data-username="<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>"
                                        data-role="<?= htmlspecialchars($u['role'] ?? 'panitia', ENT_QUOTES) ?>"
                                        class="inline-flex items-center space-x-1 text-xs font-bold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200/80 px-2.5 py-1.5 rounded-xl transition" title="Edit Akun Pengguna">
                                    <i class="fa-solid fa-pen-to-square text-[10px]"></i>
                                    <span>Edit</span>
                                </button>

                                <!-- Delete Button (Disabled for Self) -->
                                <?php if ($is_self): ?>
                                    <button type="button" disabled class="inline-flex items-center space-x-1 text-xs font-bold text-gray-300 bg-gray-50 border border-gray-200 px-2.5 py-1.5 rounded-xl cursor-not-allowed" title="Anda tidak dapat menghapus akun Anda sendiri">
                                        <i class="fa-solid fa-ban text-[10px]"></i>
                                        <span>Hapus</span>
                                    </button>
                                <?php else: ?>
                                    <button type="button" 
                                            onclick="confirmDeleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['nama_lengkap'])) ?>', '<?= htmlspecialchars(addslashes($u['username'])) ?>')"
                                            class="inline-flex items-center space-x-1 text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200/80 px-2.5 py-1.5 rounded-xl transition" title="Hapus Akun Pengguna">
                                        <i class="fa-solid fa-trash text-[10px]"></i>
                                        <span>Hapus</span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>

            <!-- ======================================================== -->
            <!-- 4B. TABLE VIEW: TABEL DATA RINGKAS (ALTERNATIF VIEW) -->
            <!-- ======================================================== -->
            <div id="view-table-container" class="hidden bg-white rounded-3xl border border-gray-200/80 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[650px] text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-500 uppercase tracking-wider text-[10px] font-bold">
                                <th class="py-3 px-4 w-12 text-center">ID</th>
                                <th class="py-3 px-4">Nama Lengkap &amp; Username</th>
                                <th class="py-3 px-4 w-48">Peran / Hak Akses</th>
                                <th class="py-3 px-4 w-44">Tanggal Terdaftar</th>
                                <th class="py-3 px-4 w-28 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($users as $u): 
                                $role_meta = get_role_badge_meta($u['role'] ?? 'panitia');
                                $is_self = ((int)$u['id'] === $current_user_id);
                                $initials = strtoupper(substr($u['nama_lengkap'] ?? $u['username'], 0, 2));
                            ?>
                                <tr class="user-item-row hover:bg-gray-50/60 transition"
                                    data-id="<?= $u['id'] ?>"
                                    data-nama="<?= htmlspecialchars(strtolower($u['nama_lengkap'])) ?>"
                                    data-username="<?= htmlspecialchars(strtolower($u['username'])) ?>"
                                    data-role="<?= htmlspecialchars(strtolower($u['role'] ?? 'panitia')) ?>">
                                    <td class="py-3 px-4 text-center font-black text-gray-400">#<?= $u['id'] ?></td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 rounded-xl <?= $role_meta['bg_avatar'] ?> flex items-center justify-center font-black text-xs flex-shrink-0" style="<?= $role_meta['avatar_style'] ?>">
                                                <?= htmlspecialchars($initials) ?>
                                            </div>
                                            <div>
                                                <span class="font-extrabold text-gray-900 text-xs block">
                                                    <?= htmlspecialchars($u['nama_lengkap']) ?>
                                                    <?php if ($is_self): ?>
                                                        <span class="inline-block text-[9px] font-bold text-emerald-700 bg-emerald-100 px-1 rounded ml-1">Akun Anda</span>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="text-[11px] font-mono text-brand-primary font-bold">@<?= htmlspecialchars($u['username']) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 whitespace-nowrap">
                                        <span class="inline-flex items-center text-[10px] font-extrabold px-2.5 py-1 rounded-md border <?= $role_meta['class'] ?>" style="<?= $role_meta['badge_style'] ?>">
                                            <i class="<?= $role_meta['icon'] ?> mr-1 text-[9px]"></i>
                                            <?= htmlspecialchars($role_meta['label']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-gray-500 font-semibold text-[11px] whitespace-nowrap">
                                        <?= !empty($u['created_at']) ? date('d M Y, H:i', strtotime($u['created_at'])) : '-' ?>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <div class="flex items-center justify-center space-x-1.5">
                                            <button type="button" 
                                                    onclick="openModalEdit(this)"
                                                    data-id="<?= $u['id'] ?>"
                                                    data-nama="<?= htmlspecialchars($u['nama_lengkap'], ENT_QUOTES) ?>"
                                                    data-username="<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>"
                                                    data-role="<?= htmlspecialchars($u['role'] ?? 'panitia', ENT_QUOTES) ?>"
                                                    class="w-7 h-7 flex items-center justify-center rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 transition" title="Edit Pengguna">
                                                <i class="fa-solid fa-pen text-[10px]"></i>
                                            </button>
                                            <?php if ($is_self): ?>
                                                <span class="w-7 h-7 flex items-center justify-center text-gray-300 cursor-not-allowed" title="Akun aktif login">
                                                    <i class="fa-solid fa-lock text-[10px]"></i>
                                                </span>
                                            <?php else: ?>
                                                <button type="button" 
                                                        onclick="confirmDeleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['nama_lengkap'])) ?>', '<?= htmlspecialchars(addslashes($u['username'])) ?>')"
                                                        class="w-7 h-7 flex items-center justify-center rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 transition" title="Hapus Pengguna">
                                                    <i class="fa-solid fa-trash text-[10px]"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Empty Search State -->
            <div id="users-empty-state" class="hidden bg-white rounded-3xl p-12 text-center text-gray-400 border border-gray-200">
                <i class="fa-solid fa-user-xmark text-4xl mb-3 block opacity-30 text-gray-400"></i>
                <h4 class="text-sm font-bold text-gray-700">Tidak Ada Pengguna yang Cocok</h4>
                <p class="text-xs text-gray-500 mt-1">Coba gunakan kata kunci pencarian atau filter role lainnya.</p>
                <button type="button" onclick="resetAllFilters()" class="mt-3 inline-flex items-center space-x-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-1.5 px-3 rounded-xl text-xs transition">
                    <i class="fa-solid fa-rotate-left text-xs"></i>
                    <span>Reset Semua Filter</span>
                </button>
            </div>

        </main>
    </div>

    <!-- ======================================================== -->
    <!-- 5. POP-UP MODAL: TAMBAH & EDIT PENGGUNA (UI-UX PRO MAX) -->
    <!-- ======================================================== -->
    <div id="modal-user" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-3 sm:p-5 overflow-y-auto">
        <div class="bg-white rounded-3xl shadow-2xl border border-gray-200/90 w-full max-w-lg my-auto relative max-h-[92vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            
            <!-- Modal Header -->
            <div class="card-header-brand flex items-center justify-between border-b border-[#3A241E] px-6 sm:px-8 py-4.5 flex-shrink-0" style="background: linear-gradient(135deg, #4A3029 0%, #5C3E35 100%) !important; color: #FFFFFF !important;">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-xl bg-brand-primary text-white flex items-center justify-center text-sm shadow-xs">
                        <i class="fa-solid fa-user-gear"></i>
                    </div>
                    <div>
                        <h2 id="modal-title" class="text-base sm:text-lg font-black text-gray-900 flex items-center">
                            Tambah Pengguna Baru
                        </h2>
                        <p class="text-[11px] text-gray-500">Lengkapi identitas staf, username unik, dan hak akses peran.</p>
                    </div>
                </div>

                <button type="button" onclick="closeModalUser()" class="w-8 h-8 rounded-xl bg-gray-150 hover:bg-rose-50 text-gray-400 hover:text-rose-600 flex items-center justify-center transition focus:outline-none">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Modal Scrollable Body -->
            <div class="p-6 sm:p-8 overflow-y-auto custom-scrollbar flex-grow space-y-4">
                <form id="form-user" action="users.php" method="POST" class="space-y-4.5" onsubmit="return handleFormSubmit(this)">
                    <input type="hidden" name="action" id="form-action" value="add">
                    <input type="hidden" name="id" id="form-id" value="">

                    <!-- Field 1: Nama Lengkap -->
                    <div class="space-y-1">
                        <label for="form-nama" class="block text-xs font-bold text-gray-700 uppercase tracking-wider">
                            Nama Lengkap Staf <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                                <i class="fa-solid fa-id-card text-xs"></i>
                            </span>
                            <input type="text" name="nama_lengkap" id="form-nama" required placeholder="Contoh: Ust. H. Abdullah Faqih, M.Pd."
                                class="w-full pl-9 pr-4 py-2.5 border border-gray-300 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 rounded-xl text-xs font-bold text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none">
                        </div>
                    </div>

                    <!-- Field 2: Username Login -->
                    <div class="space-y-1">
                        <label for="form-username" class="block text-xs font-bold text-gray-700 uppercase tracking-wider">
                            Username Login <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                                <i class="fa-solid fa-at text-xs"></i>
                            </span>
                            <input type="text" name="username" id="form-username" required placeholder="Contoh: panitia_psb, bendahara01"
                                oninput="this.value = this.value.toLowerCase().replace(/\s+/g, '')"
                                class="w-full pl-9 pr-4 py-2.5 border border-gray-300 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 rounded-xl text-xs font-mono font-bold text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none">
                        </div>
                        <p class="text-[10px] text-gray-400">Gunakan huruf kecil tanpa spasi. Digunakan untuk masuk ke Panel Admin.</p>
                    </div>

                    <!-- Field 3: Password & Toggle Visibility -->
                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <label for="form-password" class="block text-xs font-bold text-gray-700 uppercase tracking-wider">
                                Kata Sandi (Password) <span id="pass-required-star" class="text-rose-500">*</span>
                            </label>
                            <span id="pass-help-text" class="text-[10px] text-gray-400 font-semibold">Minimal 6 karakter</span>
                        </div>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                                <i class="fa-solid fa-lock text-xs"></i>
                            </span>
                            <input type="password" name="password" id="form-password" placeholder="Masukkan password..."
                                class="w-full pl-9 pr-10 py-2.5 border border-gray-300 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 rounded-xl text-xs font-bold text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none">
                            <button type="button" onclick="togglePasswordVisibility()" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none">
                                <i class="fa-solid fa-eye text-xs" id="pass-toggle-icon"></i>
                            </button>
                        </div>
                        <p id="pass-edit-note" class="hidden text-[11px] text-amber-700 bg-amber-50 p-2 rounded-lg border border-amber-200 mt-1">
                            <i class="fa-solid fa-circle-info mr-1"></i> Mode Edit: Kosongkan password jika tidak ingin mengganti kata sandi staf ini.
                        </p>
                    </div>

                    <!-- Field 4: Role / Hak Akses -->
                    <div class="space-y-1.5 pt-1">
                        <label for="form-role" class="block text-xs font-bold text-gray-700 uppercase tracking-wider">
                            Peran &amp; Wewenang Hak Akses <span class="text-rose-500">*</span>
                        </label>
                        <select name="role" id="form-role" required class="w-full border border-gray-300 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 rounded-xl px-3.5 py-2.5 text-xs font-bold text-gray-800 bg-gray-50/50 focus:bg-white transition-all focus:outline-none">
                            <option value="panitia">👥 Panitia Admisi (Input Santri, Data Pendaftar, Scan QR)</option>
                            <option value="bendahara">💰 Bendahara (Master Biaya, Verifikasi &amp; Laporan Keuangan)</option>
                            <option value="humas">📢 Humas / Konten (Berita, Sarana, Ekskul &amp; Prestasi)</option>
                            <option value="yayasan">🏛️ Pimpinan Yayasan (Monitoring Read-Only Santri &amp; Keuangan)</option>
                            <option value="superadmin">🛡️ Super Admin (Akses Penuh Seluruh Sistem &amp; Pengaturan)</option>
                        </select>
                    </div>

                    <!-- Footer Action Buttons -->
                    <div class="flex items-center justify-end space-x-2.5 pt-4 border-t border-gray-100">
                        <button type="button" onclick="closeModalUser()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2.5 px-5 rounded-xl text-xs transition">
                            Batal
                        </button>
                        <button type="submit" id="btn-submit-user" class="inline-flex items-center space-x-1.5 bg-brand-primary hover:bg-brand-deep text-white font-bold py-2.5 px-6 rounded-xl text-xs shadow-md transition transform active:scale-98">
                            <i class="fa-solid fa-paper-plane text-xs"></i>
                            <span id="btn-submit-text">Simpan Pengguna</span>
                        </button>
                    </div>

                </form>
            </div>

        </div>
    </div>

    <!-- Hidden Form for Deletion via SweetAlert2 -->
    <form id="delete-user-form" action="users.php" method="POST" class="hidden">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete-user-id" value="">
    </form>

    <!-- ======================================================== -->
    <!-- JAVASCRIPT LOGIC (SEARCH, FILTER, SORT, MODAL, SWEETALERT) -->
    <!-- ======================================================== -->
    <script>
        let currentRoleFilter = 'all';

        document.addEventListener('DOMContentLoaded', function() {
            // Restore saved view mode
            const savedView = localStorage.getItem('admin_users_view_mode') || 'grid';
            setViewMode(savedView);

            // Keyboard shortcut Esc
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const modal = document.getElementById('modal-user');
                    if (modal && !modal.classList.contains('hidden')) {
                        closeModalUser();
                    }
                }
            });
        });

        // 1. View Mode Switcher (Grid vs Table)
        function setViewMode(mode) {
            const gridEl = document.getElementById('view-grid-container');
            const tableEl = document.getElementById('view-table-container');
            const btnGrid = document.getElementById('btn-view-grid');
            const btnTable = document.getElementById('btn-view-table');

            if (mode === 'table') {
                gridEl.classList.add('hidden');
                tableEl.classList.remove('hidden');
                btnTable.classList.add('bg-brand-primary', 'text-white', 'shadow-xs');
                btnTable.classList.remove('text-gray-500', 'hover:bg-gray-200');
                btnGrid.classList.remove('bg-brand-primary', 'text-white', 'shadow-xs');
                btnGrid.classList.add('text-gray-500', 'hover:bg-gray-200');
                localStorage.setItem('admin_users_view_mode', 'table');
            } else {
                gridEl.classList.remove('hidden');
                tableEl.classList.add('hidden');
                btnGrid.classList.add('bg-brand-primary', 'text-white', 'shadow-xs');
                btnGrid.classList.remove('text-gray-500', 'hover:bg-gray-200');
                btnTable.classList.remove('bg-brand-primary', 'text-white', 'shadow-xs');
                btnTable.classList.add('text-gray-500', 'hover:bg-gray-200');
                localStorage.setItem('admin_users_view_mode', 'grid');
            }
        }

        // 2. Role Filter Tabs
        function setRoleFilter(role, btn) {
            currentRoleFilter = role;

            document.querySelectorAll('.btn-role-tab').forEach(b => {
                b.classList.remove('bg-brand-primary', 'text-white', 'shadow-xs');
                b.classList.add('text-gray-600', 'hover:bg-gray-100');
            });
            if (btn) {
                btn.classList.add('bg-brand-primary', 'text-white', 'shadow-xs');
                btn.classList.remove('text-gray-600', 'hover:bg-gray-100');
            }

            filterUsers();
        }

        // 3. Search & Filter Handling
        function filterUsers() {
            const query = (document.getElementById('search-user').value || '').toLowerCase().trim();
            const btnClear = document.getElementById('btn-clear-search');
            if (btnClear) {
                btnClear.classList.toggle('hidden', query.length === 0);
            }

            const cards = Array.from(document.querySelectorAll('.user-item-card'));
            const rows = Array.from(document.querySelectorAll('.user-item-row'));
            let visibleCount = 0;

            cards.forEach(card => {
                const nama = card.getAttribute('data-nama') || '';
                const username = card.getAttribute('data-username') || '';
                const role = card.getAttribute('data-role') || '';

                const matchesQuery = !query || nama.includes(query) || username.includes(query);
                const matchesRole = (currentRoleFilter === 'all') || (role === currentRoleFilter.toLowerCase());

                const isVisible = matchesQuery && matchesRole;
                card.style.display = isVisible ? '' : 'none';
                if (isVisible) visibleCount++;
            });

            rows.forEach(row => {
                const nama = row.getAttribute('data-nama') || '';
                const username = row.getAttribute('data-username') || '';
                const role = row.getAttribute('data-role') || '';

                const matchesQuery = !query || nama.includes(query) || username.includes(query);
                const matchesRole = (currentRoleFilter === 'all') || (role === currentRoleFilter.toLowerCase());

                row.style.display = (matchesQuery && matchesRole) ? '' : 'none';
            });

            const countEl = document.getElementById('visible-count');
            if (countEl) countEl.textContent = visibleCount;

            const emptyEl = document.getElementById('users-empty-state');
            if (emptyEl) {
                emptyEl.classList.toggle('hidden', visibleCount > 0);
            }
        }

        function clearSearch() {
            const searchInput = document.getElementById('search-user');
            if (searchInput) {
                searchInput.value = '';
                filterUsers();
                searchInput.focus();
            }
        }

        function resetAllFilters() {
            clearSearch();
            const firstTab = document.querySelector('.btn-role-tab');
            setRoleFilter('all', firstTab);
        }

        // 4. Sorting Function
        function sortUsers() {
            const sortBy = document.getElementById('sort-user').value;
            const gridContainer = document.getElementById('view-grid-container');
            const tableBody = document.querySelector('#view-table-container tbody');

            const cards = Array.from(document.querySelectorAll('.user-item-card'));
            const rows = Array.from(document.querySelectorAll('.user-item-row'));

            const sortFn = (a, b) => {
                if (sortBy === 'id_asc') {
                    return parseInt(a.getAttribute('data-id') || 0) - parseInt(b.getAttribute('data-id') || 0);
                } else if (sortBy === 'id_desc') {
                    return parseInt(b.getAttribute('data-id') || 0) - parseInt(a.getAttribute('data-id') || 0);
                } else if (sortBy === 'nama_az') {
                    return (a.getAttribute('data-nama') || '').localeCompare(b.getAttribute('data-nama') || '');
                } else if (sortBy === 'nama_za') {
                    return (b.getAttribute('data-nama') || '').localeCompare(a.getAttribute('data-nama') || '');
                }
                return 0;
            };

            cards.sort(sortFn).forEach(el => gridContainer.appendChild(el));
            rows.sort(sortFn).forEach(el => tableBody.appendChild(el));
        }

        // 5. Modal Tambah & Edit Handling
        function openModalTambah() {
            document.getElementById('modal-title').innerHTML = '<i class="fa-solid fa-user-plus text-brand-primary mr-2"></i> Tambah Pengguna Baru';
            document.getElementById('form-action').value = 'add';
            document.getElementById('form-id').value = '';
            document.getElementById('form-nama').value = '';
            document.getElementById('form-username').value = '';
            document.getElementById('form-password').value = '';
            document.getElementById('form-password').required = true;
            document.getElementById('form-role').value = 'panitia';
            document.getElementById('btn-submit-text').textContent = 'Tambah Pengguna';
            document.getElementById('pass-required-star').textContent = '*';
            document.getElementById('pass-edit-note').classList.add('hidden');

            document.getElementById('modal-user').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            setTimeout(() => document.getElementById('form-nama').focus(), 150);
        }

        function openModalEdit(btn) {
            const id = btn.getAttribute('data-id');
            const nama = btn.getAttribute('data-nama') || '';
            const username = btn.getAttribute('data-username') || '';
            const role = btn.getAttribute('data-role') || 'panitia';

            document.getElementById('modal-title').innerHTML = '<i class="fa-solid fa-user-pen text-blue-600 mr-2"></i> Edit Akun Pengguna';
            document.getElementById('form-action').value = 'edit';
            document.getElementById('form-id').value = id;
            document.getElementById('form-nama').value = nama;
            document.getElementById('form-username').value = username;
            document.getElementById('form-password').value = '';
            document.getElementById('form-password').required = false;
            document.getElementById('form-role').value = role;
            document.getElementById('btn-submit-text').textContent = 'Simpan Perubahan';
            document.getElementById('pass-required-star').textContent = '';
            document.getElementById('pass-edit-note').classList.remove('hidden');

            document.getElementById('modal-user').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            setTimeout(() => document.getElementById('form-nama').focus(), 150);
        }

        function closeModalUser() {
            document.getElementById('modal-user').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        // 6. Password Visibility Toggle
        function togglePasswordVisibility() {
            const passInput = document.getElementById('form-password');
            const icon = document.getElementById('pass-toggle-icon');
            if (passInput.type === 'password') {
                passInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // 7. Form Submit Loading Protection
        function handleFormSubmit(form) {
            const passInput = document.getElementById('form-password');
            const action = document.getElementById('form-action').value;
            if (action === 'add' && passInput.value.length < 6) {
                alert('Password minimal harus 6 karakter.');
                passInput.focus();
                return false;
            }
            if (action === 'edit' && passInput.value.length > 0 && passInput.value.length < 6) {
                alert('Password baru minimal harus 6 karakter.');
                passInput.focus();
                return false;
            }

            const btn = document.getElementById('btn-submit-user');
            const txt = document.getElementById('btn-submit-text');
            if (btn && txt) {
                btn.disabled = true;
                txt.textContent = 'Menyimpan...';
            }
            return true;
        }

        // 8. SweetAlert2 Confirmation for Deletion
        function confirmDeleteUser(id, nama, username) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Hapus Akun Pengguna?',
                    html: `Apakah Anda yakin ingin menghapus akun staf:<br><strong class="text-rose-700 text-base">"${nama}" (@${username})</strong>?<br><br><span class="text-xs text-gray-500">Staf ini tidak akan dapat login lagi ke Panel Admin Pondok Pesantren Babul Jannah.</span>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#E11D48',
                    cancelButtonColor: '#6B7280',
                    confirmButtonText: '<i class="fa-solid fa-trash mr-1.5"></i> Ya, Hapus Pengguna',
                    cancelButtonText: 'Batal',
                    reverseButtons: true,
                    customClass: {
                        popup: 'rounded-3xl p-6',
                        confirmButton: 'rounded-xl font-bold px-4 py-2.5 text-xs shadow-md',
                        cancelButton: 'rounded-xl font-bold px-4 py-2.5 text-xs'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('delete-user-id').value = id;
                        document.getElementById('delete-user-form').submit();
                    }
                });
            } else {
                if (confirm(`Apakah Anda yakin ingin menghapus akun "${nama}" (@${username})?`)) {
                    document.getElementById('delete-user-id').value = id;
                    document.getElementById('delete-user-form').submit();
                }
            }
        }
    </script>

</body>
</html>
