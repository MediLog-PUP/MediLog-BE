<?php
session_start();
require '../db_connect.php'; 

if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'faculty') {
        header("Location: ../admin/admin_dashboard.php");
        exit();
    } elseif ($_SESSION['role'] === 'student') {
        header("Location: ../student/student_dashboard.php");
        exit();
    }
}

$error_msg = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_number = trim($_POST['student-number']);
    $password = $_POST['password'];

    if (empty($id_number) || empty($password)) {
        $error_msg = "Please enter both Student Number and Password.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id_number = ? AND role = 'student'");
            $stmt->execute([$id_number]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                
                header("Location: ../student/student_dashboard.php");
                exit();
            } else {
                $error_msg = "Invalid Student Number or Password.";
            }
        } catch(PDOException $e) {
            $error_msg = "System error. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login - MediLog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] }, colors: { pup: { maroon: '#880000', maroonDark: '#660000', gold: '#F1B500', goldLight: '#FDE68A' } } } } }
    </script>
    <style>
        body { opacity: 0; animation: fadeIn 0.5s ease-out forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .page-exit { animation: fadeOut 0.3s ease-in forwards !important; }
        @keyframes fadeOut { from { opacity: 1; transform: translateY(0); } to { opacity: 0; transform: translateY(-10px); } }
        .glass-panel { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); }
    </style>
</head>
<body class="font-sans antialiased text-gray-800 min-h-screen relative flex items-center justify-center bg-gray-50 overflow-x-hidden">

    <!-- Decorative Background Elements -->
    <div class="absolute top-[-10%] left-[-10%] w-96 h-96 bg-pup-maroon rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-96 h-96 bg-pup-gold rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-2000"></div>

    <div class="w-full max-w-5xl mx-auto p-4 sm:p-6 lg:p-8 relative z-10 flex flex-col lg:flex-row items-stretch min-h-[600px] shadow-2xl rounded-3xl overflow-hidden bg-white/50 border border-white/40">
        
        <!-- Left Branding Panel -->
        <div class="w-full lg:w-5/12 bg-pup-maroon p-8 sm:p-12 flex flex-col justify-between relative overflow-hidden rounded-t-3xl lg:rounded-l-3xl lg:rounded-tr-none">
            <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80')] bg-cover bg-center opacity-10 mix-blend-overlay"></div>
            <div class="absolute inset-0 bg-gradient-to-br from-pup-maroonDark/90 to-pup-maroon/40"></div>
            
            <div class="relative z-10 flex items-center gap-3 text-white mb-8">
                <div class="bg-white/20 p-2 rounded-xl backdrop-blur-sm border border-white/30">
                    <i data-lucide="clipboard-heart" class="h-6 w-6 text-pup-gold"></i>
                </div>
                <span class="text-xl font-bold tracking-wider uppercase">MediLog</span>
            </div>

            <div class="relative z-10 mt-8 lg:mt-0">
                <h2 class="text-3xl sm:text-4xl font-extrabold text-white mb-4 leading-tight">Iskolar ng <br><span class="text-pup-gold">Bayan.</span></h2>
                <p class="text-white/80 text-base sm:text-lg mb-8">Access your digital health records, book clinic appointments, and request medical clearances seamlessly.</p>
            </div>
            
            <div class="relative z-10 hidden lg:flex items-center gap-3 text-white/70 text-sm">
                <i data-lucide="shield-check" class="h-5 w-5 text-pup-gold"></i> Secure University Portal
            </div>
        </div>

        <!-- Right Form Panel -->
        <div class="w-full lg:w-7/12 glass-panel p-8 sm:p-12 flex flex-col justify-center rounded-b-3xl lg:rounded-r-3xl lg:rounded-bl-none">
            
            <a href="../index.php" class="inline-flex items-center text-sm font-semibold text-gray-500 hover:text-pup-maroon transition-colors mb-8 w-max group">
                <i data-lucide="arrow-left" class="mr-2 h-4 w-4 transform group-hover:-translate-x-1 transition-transform"></i> Back
            </a>

            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Student Login</h1>
                <p class="text-gray-500">Sign in to your account to continue.</p>
            </div>

            <?php if($error_msg): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 flex items-start gap-3 text-sm font-medium animate-bounce">
                    <i data-lucide="alert-circle" class="h-5 w-5 flex-shrink-0 mt-0.5"></i>
                    <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-5">
                <div>
                    <label for="student-number" class="block text-sm font-medium text-gray-700 mb-1.5">Student Number</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i data-lucide="user" class="h-5 w-5 text-gray-400"></i>
                        </div>
                        <input type="text" id="student-number" name="student-number" class="block w-full pl-11 pr-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-pup-maroon focus:border-transparent sm:text-sm transition-all bg-gray-50 focus:bg-white" placeholder="2021-00000-MN-0" required value="<?php echo isset($_POST['student-number']) ? htmlspecialchars($_POST['student-number']) : ''; ?>">
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                        <a href="forgotpassword.php" class="text-sm font-semibold text-pup-maroon hover:text-pup-maroonDark transition-colors">Forgot password?</a>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i data-lucide="lock" class="h-5 w-5 text-gray-400"></i>
                        </div>
                        <input type="password" id="password" name="password" class="block w-full pl-11 pr-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-pup-maroon focus:border-transparent sm:text-sm transition-all bg-gray-50 focus:bg-white" placeholder="••••••••" required>
                    </div>
                </div>

                <button type="submit" class="w-full flex justify-center items-center py-3.5 px-4 rounded-xl shadow-md text-sm font-bold text-white bg-pup-maroon hover:bg-pup-maroonDark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-pup-maroon transition-all transform hover:-translate-y-0.5 mt-4">
                    Sign In <i data-lucide="log-in" class="ml-2 h-4 w-4"></i>
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-gray-100 text-center text-sm text-gray-500">
                Don't have an account? <a href="register.php" class="font-bold text-pup-maroon hover:text-pup-maroonDark transition-colors">Register here</a>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
        document.addEventListener('DOMContentLoaded', () => {
            const links = document.querySelectorAll('a[href]:not([href^="#"]):not([target="_blank"])');
            links.forEach(link => {
                link.addEventListener('click', e => {
                    const href = link.getAttribute('href');
                    if(href === "javascript:void(0);" || href === "") return;
                    e.preventDefault();
                    document.body.classList.add('page-exit');
                    setTimeout(() => window.location.href = href, 250); 
                });
            });
        });
    </script>
</body>
</html>