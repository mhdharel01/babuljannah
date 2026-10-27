<?php
// admin/login.php
require_once '../config/db.php';

// Jika sudah login, langsung ke dashboard
if (isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = sanitize($_POST['password'] ?? '');
    $captcha = sanitize($_POST['captcha'] ?? '');
    
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Sesi telah berakhir atau token tidak valid. Silakan muat ulang halaman.";
    } elseif (!isset($_SESSION['captcha_answer']) || $captcha !== (string)$_SESSION['captcha_answer']) {
        $error = "Jawaban perhitungan salah. Silakan coba lagi.";
    } elseif (!empty($username) && !empty($password)) {
        try {
            $stmt = $db->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Set Session
                $_SESSION['admin_logged'] = true;
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_nama'] = $user['nama_lengkap'];
                $_SESSION['admin_role'] = $user['role'];
                
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Username atau password salah.";
            }
        } catch (PDOException $e) {
            $error = "Terjadi kesalahan database: " . $e->getMessage();
        }
    } else {
        $error = "Silakan isi semua field.";
    }
}

// Generate Captcha Baru
$num1 = rand(1, 9);
$num2 = rand(1, 9);
$_SESSION['captcha_answer'] = $num1 + $num2;
$_SESSION['captcha_question'] = "$num1 + $num2";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Panitia PSB - Babul Jannah</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3E2723',
                        secondary: '#8D6E63',
                        accent: '#F5F0EB',
                        dark: '#212121'
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-12px); }
        }
        .float-anim { animation: float 6s ease-in-out infinite; }
        .float-anim-delay { animation: float 6s ease-in-out 2s infinite; }
    </style>
</head>
<body class="bg-accent min-h-screen flex font-sans">

    <!-- Left Panel: Branding Visual -->
    <div class="hidden lg:flex lg:w-[55%] bg-primary relative overflow-hidden flex-col justify-between p-12 min-h-screen">
        <!-- Decorative Background Elements -->
        <div class="absolute inset-0 bg-gradient-to-br from-primary via-primary to-[#2C1A14]"></div>
        <div class="absolute top-0 right-0 w-96 h-96 bg-secondary/10 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-0 left-0 w-72 h-72 bg-secondary/8 rounded-full blur-[100px]"></div>
        
        <!-- Floating Geometric Decorations -->
        <div class="absolute top-20 right-16 w-20 h-20 border border-white/5 rounded-3xl rotate-12 float-anim"></div>
        <div class="absolute bottom-32 right-24 w-14 h-14 border border-white/8 rounded-2xl -rotate-12 float-anim-delay"></div>
        <div class="absolute top-1/2 left-12 w-10 h-10 bg-secondary/10 rounded-xl rotate-45 float-anim-delay"></div>

        <!-- Top: Brand Logo -->
        <div class="relative z-10 flex items-center space-x-4">
            <div class="w-14 h-14 bg-accent/10 backdrop-blur-sm border border-white/10 rounded-2xl flex items-center justify-center text-accent text-2xl shadow-xl">
                <i class="fa-solid fa-mosque"></i>
            </div>
            <div>
                <h2 class="text-lg font-extrabold text-white tracking-wide">BABUL JANNAH</h2>
                <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-[0.2em]">Pondok Pesantren</p>
            </div>
        </div>

        <!-- Center: Hero Text -->
        <div class="relative z-10 space-y-6 max-w-md">
            <h1 class="text-4xl xl:text-5xl font-extrabold text-white leading-tight tracking-tight">
                Panel Administrasi
                <span class="block text-secondary font-serif italic font-normal mt-1">Panitia PSB</span>
            </h1>
            <p class="text-sm text-gray-300 leading-relaxed font-normal">
                Kelola pendaftaran santri baru, verifikasi pembayaran, kontrol gelombang, berita, dan fasilitas pondok pesantren melalui satu dashboard terpadu.
            </p>
            <div class="flex items-center space-x-6 pt-2">
                <div class="flex items-center space-x-2 text-gray-400 text-xs">
                    <div class="w-8 h-8 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center">
                        <i class="fa-solid fa-shield-halved text-secondary text-sm"></i>
                    </div>
                    <span>Aman & Terenkripsi</span>
                </div>
                <div class="flex items-center space-x-2 text-gray-400 text-xs">
                    <div class="w-8 h-8 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center">
                        <i class="fa-solid fa-bolt text-secondary text-sm"></i>
                    </div>
                    <span>Real-time</span>
                </div>
            </div>
        </div>

        <!-- Bottom: Footer -->
        <div class="relative z-10 flex items-center justify-between border-t border-white/5 pt-6">
            <p class="text-[10px] text-gray-500 font-medium">&copy; <?= date('Y') ?> PP Babul Jannah — Dumai, Riau</p>
            <div class="flex items-center space-x-1 text-[10px] text-gray-500">
                <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                <span>Sistem Aktif</span>
            </div>
        </div>
    </div>

    <!-- Right Panel: Login Form -->
    <div class="w-full lg:w-[45%] flex items-center justify-center p-6 sm:p-12">
        <div class="max-w-sm w-full space-y-8">
            
            <!-- Mobile Brand (visible on small screens only) -->
            <div class="lg:hidden text-center space-y-3">
                <div class="w-16 h-16 bg-primary rounded-2xl flex items-center justify-center text-accent text-3xl font-bold border-2 border-secondary mx-auto shadow-lg">
                    <i class="fa-solid fa-mosque"></i>
                </div>
                <h1 class="text-xl font-extrabold text-primary tracking-wide">PANEL ADMIN PSB</h1>
                <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wider">Ponpes Babul Jannah</p>
            </div>

            <!-- Form Header -->
            <div class="space-y-2 hidden lg:block">
                <h2 class="text-2xl font-extrabold text-primary tracking-tight">Selamat Datang 👋</h2>
                <p class="text-xs text-gray-500 font-normal leading-relaxed">Masukkan kredensial akun panitia PSB untuk mengakses dashboard administrasi.</p>
            </div>

            <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3.5 rounded-2xl text-xs font-medium flex items-center space-x-2.5 shadow-sm">
                <div class="w-8 h-8 rounded-xl bg-red-100 flex items-center justify-center flex-shrink-0">
                    <i class="fa-solid fa-triangle-exclamation text-red-600 text-sm"></i>
                </div>
                <span><?= $error ?></span>
            </div>
            <?php endif; ?>

            <!-- Form Login -->
            <form action="login.php" method="POST" class="space-y-5">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                
                <div class="space-y-2">
                    <label for="username" class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">Username</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400">
                            <i class="fa-solid fa-user text-xs"></i>
                        </span>
                        <input type="text" name="username" id="username" autocomplete="username" required placeholder="Masukkan username"
                            class="w-full bg-white border border-gray-200 focus:border-secondary focus:ring-2 focus:ring-secondary/20 rounded-2xl pl-11 pr-4 py-3.5 text-sm focus:outline-none transition-all duration-300 font-medium">
                    </div>
                </div>

                <div class="space-y-2">
                    <label for="password" class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400">
                            <i class="fa-solid fa-lock text-xs"></i>
                        </span>
                        <input type="password" name="password" id="password" autocomplete="current-password" required placeholder="Masukkan password"
                            class="w-full bg-white border border-gray-200 focus:border-secondary focus:ring-2 focus:ring-secondary/20 rounded-2xl pl-11 pr-12 py-3.5 text-sm focus:outline-none transition-all duration-300 font-medium">
                        <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-primary transition">
                            <i id="eye-icon" class="fa-solid fa-eye text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- Keamanan Captcha Matematika -->
                <div class="space-y-2">
                    <label for="captcha" class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest">Verifikasi Keamanan</label>
                    <div class="flex items-center space-x-3">
                        <div class="bg-primary text-white font-extrabold text-lg px-4 py-2.5 rounded-xl border-2 border-secondary/50 shadow-inner flex items-center justify-center min-w-[100px] select-none">
                            <?= $_SESSION['captcha_question'] ?> = ?
                        </div>
                        <div class="relative flex-1">
                            <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400">
                                <i class="fa-solid fa-calculator text-xs"></i>
                            </span>
                            <input type="number" name="captcha" id="captcha" required placeholder="Hasil hitung"
                                class="w-full bg-white border border-gray-200 focus:border-secondary focus:ring-2 focus:ring-secondary/20 rounded-2xl pl-11 pr-4 py-3.5 text-sm focus:outline-none transition-all duration-300 font-bold text-center">
                        </div>
                    </div>
                </div>

                <div class="pt-1">
                    <button type="submit" class="w-full bg-primary hover:bg-secondary text-white font-extrabold py-4 rounded-2xl text-sm shadow-lg transition-all duration-300 transform hover:-translate-y-0.5 hover:shadow-xl flex items-center justify-center space-x-2">
                        <i class="fa-solid fa-right-to-bracket text-sm"></i>
                        <span>Masuk ke Dashboard</span>
                    </button>
                </div>
            </form>

            <div class="text-center pt-4 border-t border-gray-100">
                <a href="../index.php" class="text-xs text-secondary hover:text-primary font-bold transition-colors duration-200 inline-flex items-center space-x-1.5">
                    <i class="fa-solid fa-arrow-left text-[10px]"></i>
                    <span>Kembali ke Website Publik</span>
                </a>
            </div>
        </div>
    </div>

    <script>
    function togglePassword() {
        const pw = document.getElementById('password');
        const icon = document.getElementById('eye-icon');
        if (pw.type === 'password') {
            pw.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            pw.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
    </script>
</body>
</html>
