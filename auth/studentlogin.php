<?php
session_start();
require '../db_connect.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'student') header("Location: ../student/student_dashboard.php");
    else header("Location: ../admin/admin_dashboard.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_number = trim($_POST['id_number']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id_number = ? AND role = 'student'");
    $stmt->execute([$id_number]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        header("Location: ../student/student_dashboard.php");
        exit();
    } else {
        $error = "Invalid Student ID or Password.";
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
</head>
<body class="font-sans antialiased text-gray-800 bg-gray-50 flex items-center justify-center min-h-screen relative overflow-hidden bg-[url('https://www.transparenttextures.com/patterns/cubes.png')]">

    <!-- Global Loader -->
    <?php include '../global_loader.php'; ?>

    <div class="absolute top-0 w-full h-2 bg-pup-maroon"></div>

    <div class="w-full max-w-md p-8 sm:p-10 bg-white rounded-3xl shadow-xl border border-gray-100 z-10 mx-4">
        <div class="flex justify-center mb-6">
            <div class="bg-pup-maroon p-4 rounded-2xl shadow-lg">
                <i data-lucide="clipboard-heart" class="h-10 w-10 text-pup-gold"></i>
            </div>
        </div>
        
        <h2 class="text-2xl font-extrabold text-center text-gray-900 mb-2">Student Portal</h2>
        <p class="text-center text-gray-500 text-sm mb-8">Sign in to manage your health records</p>

        <?php if($error): ?>
            <div class="bg-red-50 text-red-600 p-4 rounded-xl text-sm font-semibold mb-6 flex items-center gap-2 border border-red-100">
                <i data-lucide="alert-circle" class="h-5 w-5"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="studentlogin.php" method="POST" class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Student ID Number</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400">
                        <i data-lucide="user" class="h-5 w-5"></i>
                    </div>
                    <input type="text" name="id_number" required placeholder="e.g. 2021-00000-MN-0" class="block w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm bg-gray-50 focus:bg-white transition-colors">
                </div>
            </div>
            
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-sm font-medium text-gray-700">Password</label>
                    <a href="forgot_password.php" class="text-xs font-semibold text-pup-maroon hover:underline">Forgot password?</a>
                </div>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400">
                        <i data-lucide="lock" class="h-5 w-5"></i>
                    </div>
                    <input type="password" name="password" required placeholder="••••••••" class="block w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm bg-gray-50 focus:bg-white transition-colors">
                </div>
            </div>

            <button type="submit" class="w-full flex justify-center py-3.5 px-4 border border-transparent rounded-xl shadow-sm text-sm font-bold text-white bg-pup-maroon hover:bg-pup-maroonDark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-pup-maroon transition-all transform hover:-translate-y-0.5 mt-2">
                Sign In
            </button>
        </form>

        <div class="mt-8 text-center space-y-3">
            <p class="text-sm text-gray-500">
                New student? <a href="register.php" class="font-bold text-pup-maroon hover:underline">Create an account</a>
            </p>
            <div class="border-t border-gray-100 pt-4">
                <a href="facultylogin.php" class="text-sm font-semibold text-gray-600 hover:text-gray-900 flex items-center justify-center gap-2 transition-colors">
                    <i data-lucide="shield-half" class="h-4 w-4"></i> Clinic Admin Login
                </a>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>