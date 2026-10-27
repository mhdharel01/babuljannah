<?php
// admin/users.php
require_once '../config/db.php';

// Cek login & Proteksi Role
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: login.php');
    exit;
}
if ($_SESSION['admin_role'] !== 'superadmin') {
    die("Akses Ditolak. Halaman ini hanya untuk Super Admin.");
}

$success_msg = '';
$error_msg = '';

// Handle CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $username = sanitize($_POST['username']);
        $password = $_POST['password']; // will hash
        $nama_lengkap = sanitize($_POST['nama_lengkap']);
        $role = sanitize($_POST['role']);
        
        if (empty($username) || empty($password) || empty($nama_lengkap) || empty($role)) {
            $error_msg = "Semua field harus diisi.";
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, password, nama_lengkap, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $hash, $nama_lengkap, $role]);
                $success_msg = "Pengguna baru berhasil ditambahkan.";
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $error_msg = "Username sudah digunakan.";
                } else {
                    $error_msg = "Gagal menambah: " . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['id'];
        $username = sanitize($_POST['username']);
        $nama_lengkap = sanitize($_POST['nama_lengkap']);
        $role = sanitize($_POST['role']);
        $password = $_POST['password'] ?? '';
        
        try {
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET username = ?, nama_lengkap = ?, role = ?, password = ? WHERE id = ?");
                $stmt->execute([$username, $nama_lengkap, $role, $hash, $id]);
            } else {
                $stmt = $db->prepare("UPDATE users SET username = ?, nama_lengkap = ?, role = ? WHERE id = ?");
                $stmt->execute([$username, $nama_lengkap, $role, $id]);
            }
            $success_msg = "Pengguna berhasil diupdate.";
            
            // Jika mengupdate diri sendiri, perbarui session
            if ($id === $_SESSION['admin_id']) {
                $_SESSION['admin_nama'] = $nama_lengkap;
                $_SESSION['admin_role'] = $role;
            }
        } catch (PDOException $e) {
            $error_msg = "Gagal mengupdate: " . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id === $_SESSION['admin_id']) {
            $error_msg = "Anda tidak dapat menghapus akun Anda sendiri saat sedang login.";
        } else {
            try {
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $success_msg = "Pengguna berhasil dihapus.";
            } catch (PDOException $e) {
                $error_msg = "Gagal menghapus: " . $e->getMessage();
            }
        }
    }
}

// Fetch all users
$users = $db->query("SELECT * FROM users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

?>
<?php
include 'includes/head.php';
?>
<body class="bg-gray-100 flex h-screen overflow-hidden">
    
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-grow flex flex-col h-full overflow-hidden">
        
        <!-- Header -->
        <?php include 'includes/header.php'; ?>

        <!-- Main Content -->
        <main class="flex-grow overflow-y-auto p-6 lg:p-10 space-y-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold text-primary flex items-center">
                        <i class="fa-solid fa-users-gear mr-3"></i> Manajemen Pengguna (Admin)
                    </h1>
                    <p class="text-gray-500 mt-2 font-medium">Kelola hak akses dan peran panitia PSB.</p>
                </div>
                <button onclick="openModal('add')" class="bg-primary hover:bg-secondary text-white px-6 py-3 rounded-xl font-bold text-sm shadow-md transition-all flex items-center">
                    <i class="fa-solid fa-plus mr-2"></i> Tambah Pengguna Baru
                </button>
            </div>

            <?php if ($success_msg): ?>
                <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-xl shadow-sm mb-6 flex items-center">
                    <i class="fa-solid fa-circle-check text-emerald-500 text-xl mr-3"></i>
                    <p class="text-emerald-800 font-medium"><?= htmlspecialchars($success_msg) ?></p>
                </div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-xl shadow-sm mb-6 flex items-center">
                    <i class="fa-solid fa-circle-exclamation text-red-500 text-xl mr-3"></i>
                    <p class="text-red-800 font-medium"><?= htmlspecialchars($error_msg) ?></p>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead>
                            <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-600 uppercase tracking-wider font-bold text-xs">
                                <th class="px-6 py-4 text-center">ID</th>
                                <th class="px-6 py-4">Username</th>
                                <th class="px-6 py-4">Nama Lengkap</th>
                                <th class="px-6 py-4">Role (Hak Akses)</th>
                                <th class="px-6 py-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <?php foreach ($users as $u): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 text-center font-bold text-gray-400"><?= $u['id'] ?></td>
                                    <td class="px-6 py-4 font-bold text-primary"><?= htmlspecialchars($u['username']) ?></td>
                                    <td class="px-6 py-4 font-semibold"><?= htmlspecialchars($u['nama_lengkap']) ?></td>
                                    <td class="px-6 py-4">
                                        <?php
                                        $role_badge = '';
                                        if ($u['role'] === 'superadmin') $role_badge = '<span class="bg-red-100 text-red-700 px-2 py-1 rounded text-xs font-bold">Super Admin</span>';
                                        elseif ($u['role'] === 'bendahara') $role_badge = '<span class="bg-blue-100 text-blue-700 px-2 py-1 rounded text-xs font-bold">Bendahara</span>';
                                        elseif ($u['role'] === 'humas') $role_badge = '<span class="bg-amber-100 text-amber-700 px-2 py-1 rounded text-xs font-bold">Humas / Konten</span>';
                                        elseif ($u['role'] === 'yayasan') $role_badge = '<span class="bg-purple-100 text-purple-700 px-2 py-1 rounded text-xs font-bold">Yayasan (Read Only)</span>';
                                        else $role_badge = '<span class="bg-emerald-100 text-emerald-700 px-2 py-1 rounded text-xs font-bold">Panitia PSB</span>';
                                        echo $role_badge;
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex justify-center space-x-2">
                                            <button type="button" onclick="openModal('edit', <?= htmlspecialchars(json_encode($u)) ?>)" class="bg-amber-50 text-amber-600 hover:bg-amber-100 p-2 rounded-xl border border-amber-200 transition" title="Edit">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                            <?php if ($u['id'] !== $_SESSION['admin_id']): ?>
                                            <form action="" method="POST" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus user ini?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="bg-red-50 text-red-600 hover:bg-red-100 p-2 rounded-xl border border-red-200 transition" title="Hapus">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Form -->
    <div id="userModal" class="fixed inset-0 z-50 flex items-center justify-center hidden">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeModal()"></div>
        <div class="bg-white rounded-3xl w-full max-w-lg shadow-2xl relative z-10 transform scale-95 opacity-0 transition-all duration-300" id="modalContent">
            <div class="flex justify-between items-center p-6 border-b border-gray-100">
                <h3 class="text-xl font-bold text-primary" id="modalTitle">Tambah Pengguna Baru</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-red-500 transition">
                    <i class="fa-solid fa-xmark text-2xl"></i>
                </button>
            </div>
            <form action="" method="POST" class="p-6">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="formId" value="">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" id="formNama" required class="w-full border border-gray-300 rounded-xl px-4 py-2 focus:ring-2 focus:ring-secondary/20 focus:border-secondary outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Username (Login)</label>
                        <input type="text" name="username" id="formUsername" required class="w-full border border-gray-300 rounded-xl px-4 py-2 focus:ring-2 focus:ring-secondary/20 focus:border-secondary outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Password</label>
                        <input type="password" name="password" id="formPassword" class="w-full border border-gray-300 rounded-xl px-4 py-2 focus:ring-2 focus:ring-secondary/20 focus:border-secondary outline-none" placeholder="Kosongkan jika tidak ingin mengubah password (saat edit)">
                        <p class="text-xs text-gray-500 mt-1" id="passwordHelp">Isi password untuk login. Saat edit, kosongkan jika tidak ingin diubah.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Role / Hak Akses</label>
                        <select name="role" id="formRole" required class="w-full border border-gray-300 rounded-xl px-4 py-2 focus:ring-2 focus:ring-secondary/20 focus:border-secondary outline-none">
                            <option value="panitia">Panitia PSB (Data Pendaftar)</option>
                            <option value="bendahara">Bendahara (Keuangan)</option>
                            <option value="humas">Humas / Konten (Berita & Profil)</option>
                            <option value="yayasan">Yayasan / Pimpinan (Read-Only)</option>
                            <option value="superadmin">Super Admin (Akses Penuh)</option>
                        </select>
                    </div>
                </div>

                <div class="mt-8 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal()" class="px-5 py-2.5 bg-gray-100 text-gray-700 font-bold rounded-xl hover:bg-gray-200 transition">Batal</button>
                    <button type="submit" class="px-5 py-2.5 bg-primary text-white font-bold rounded-xl hover:bg-secondary transition shadow-md">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('userModal');
        const modalContent = document.getElementById('modalContent');
        const formAction = document.getElementById('formAction');
        const formId = document.getElementById('formId');
        const modalTitle = document.getElementById('modalTitle');
        const formPassword = document.getElementById('formPassword');

        function openModal(mode, data = null) {
            modal.classList.remove('hidden');
            setTimeout(() => {
                modalContent.classList.remove('scale-95', 'opacity-0');
                modalContent.classList.add('scale-100', 'opacity-100');
            }, 10);

            if (mode === 'add') {
                formAction.value = 'add';
                formId.value = '';
                modalTitle.textContent = 'Tambah Pengguna Baru';
                document.getElementById('formNama').value = '';
                document.getElementById('formUsername').value = '';
                document.getElementById('formRole').value = 'panitia';
                formPassword.required = true;
            } else if (mode === 'edit' && data) {
                formAction.value = 'edit';
                formId.value = data.id;
                modalTitle.textContent = 'Edit Pengguna';
                document.getElementById('formNama').value = data.nama_lengkap;
                document.getElementById('formUsername').value = data.username;
                document.getElementById('formRole').value = data.role;
                formPassword.required = false;
                formPassword.value = '';
            }
        }

        function closeModal() {
            modalContent.classList.remove('scale-100', 'opacity-100');
            modalContent.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }
    </script>
</body>
</html>
