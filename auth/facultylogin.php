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

    // Updated to allow super_admin to log in!
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id_number = ? AND role IN ('admin', 'faculty', 'super_admin')");
    $stmt->execute([$id_number]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        header("Location: ../admin/admin_dashboard.php");
        exit();
    } else {
        $error = "Invalid Employee ID or Password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - MediLog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] }, colors: { pup: { maroon: '#880000', maroonDark: '#660000', gold: '#F1B500', goldLight: '#FDE68A' } } } } }
    </script>
</head>
<body class="font-sans antialiased text-gray-800 bg-gray-900 flex items-center justify-center min-h-screen relative overflow-hidden bg-[url('https://www.transparenttextures.com/patterns/cubes.png')]">

    <!-- Global Loader -->
    <?php include '../global_loader.php'; ?>

    <div class="absolute top-0 w-full h-2 bg-pup-gold"></div>

    <div class="w-full max-w-md p-8 sm:p-10 bg-white rounded-3xl shadow-2xl border border-gray-100 z-10 mx-4 relative overflow-hidden">
        
        <div class="flex justify-center mb-6 relative z-10">
            <div class="bg-gray-900 p-4 rounded-2xl shadow-lg border border-gray-800">
                <i data-lucide="shield-plus" class="h-10 w-10 text-pup-gold"></i>
            </div>
        </div>
        
        <h2 class="text-2xl font-extrabold text-center text-gray-900 mb-2 relative z-10">Clinic Admin Portal</h2>
        <p class="text-center text-gray-500 text-sm mb-8 relative z-10">Secure access for authorized faculty only</p>

        <?php if($error): ?>
            <div class="bg-red-50 text-red-600 p-4 rounded-xl text-sm font-semibold mb-6 flex items-center gap-2 border border-red-100 relative z-10">
                <i data-lucide="alert-circle" class="h-5 w-5"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="facultylogin.php" method="POST" class="space-y-5 relative z-10">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Employee ID</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400">
                        <i data-lucide="briefcase" class="h-5 w-5"></i>
                    </div>
                    <input type="text" name="id_number" required placeholder="e.g. EMP-12345" class="block w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-gray-900 focus:border-gray-900 sm:text-sm bg-gray-50 focus:bg-white transition-colors">
                </div>
            </div>
            
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-sm font-medium text-gray-700">Admin Password</label>
                    <a href="forgot_password.php" class="text-xs font-semibold text-gray-500 hover:text-gray-900 hover:underline">Recovery</a>
                </div>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400">
                        <i data-lucide="lock" class="h-5 w-5"></i>
                    </div>
                    <input type="password" name="password" required placeholder="••••••••" class="block w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-gray-900 focus:border-gray-900 sm:text-sm bg-gray-50 focus:bg-white transition-colors">
                </div>
            </div>

            <button type="submit" class="w-full flex justify-center py-3.5 px-4 border border-transparent rounded-xl shadow-sm text-sm font-bold text-gray-900 bg-pup-gold hover:bg-yellow-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-pup-gold transition-all transform hover:-translate-y-0.5 mt-2">
                Secure Sign In
            </button>
        </form>

        <div class="mt-8 text-center border-t border-gray-100 pt-6 relative z-10">
            <a href="studentlogin.php" class="text-sm font-semibold text-gray-500 hover:text-pup-maroon flex items-center justify-center gap-2 transition-colors">
                <i data-lucide="arrow-left" class="h-4 w-4"></i> Return to Student Login
            </a>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>