<?php
session_start();
require '../db_connect.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../student/student_dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $id_number = trim($_POST['id_number']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if ID exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id_number = ?");
        $stmt->execute([$id_number]);
        if ($stmt->fetch()) {
            $error = "Student ID already exists in the system.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare("INSERT INTO users (full_name, id_number, email, password, role) VALUES (?, ?, ?, ?, 'student')");
            if ($insert->execute([$full_name, $id_number, $email, $hashed_password])) {
                $success = "Registration successful! You may now log in.";
            } else {
                $error = "An error occurred during registration. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MediLog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] }, colors: { pup: { maroon: '#880000', maroonDark: '#660000', gold: '#F1B500', goldLight: '#FDE68A' } } } } }
    </script>
</head>
<body class="font-sans antialiased text-gray-800 bg-gray-50 flex items-center justify-center min-h-screen py-10 overflow-x-hidden relative bg-[url('https://www.transparenttextures.com/patterns/cubes.png')]">

    <!-- Global Loader -->
    <?php include '../global_loader.php'; ?>

    <div class="absolute top-0 w-full h-2 bg-pup-maroon"></div>

    <div class="w-full max-w-lg p-8 sm:p-10 bg-white rounded-3xl shadow-xl border border-gray-100 z-10 mx-4">
        
        <h2 class="text-2xl font-extrabold text-gray-900 mb-2">Create an Account</h2>
        <p class="text-gray-500 text-sm mb-8">Register your student ID to access the MediLog clinic portal.</p>

        <?php if($error): ?>
            <div class="bg-red-50 text-red-600 p-4 rounded-xl text-sm font-semibold mb-6 flex items-center gap-2 border border-red-100">
                <i data-lucide="alert-circle" class="h-5 w-5"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="bg-green-50 text-green-700 p-4 rounded-xl text-sm font-semibold mb-6 flex items-center gap-2 border border-green-100">
                <i data-lucide="check-circle-2" class="h-5 w-5"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST" class="space-y-4">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Full Name</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400"><i data-lucide="type" class="h-5 w-5"></i></div>
                    <input type="text" name="full_name" required placeholder="Juan Dela Cruz" class="block w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm bg-gray-50 focus:bg-white transition-colors">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Student ID</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400"><i data-lucide="id-card" class="h-4 w-4"></i></div>
                        <input type="text" name="id_number" required placeholder="202X-00000" class="block w-full pl-9 pr-3 py-3 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm bg-gray-50 focus:bg-white transition-colors">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email Address</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400"><i data-lucide="mail" class="h-4 w-4"></i></div>
                        <input type="email" name="email" required placeholder="student@iskolar.edu.ph" class="block w-full pl-9 pr-3 py-3 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm bg-gray-50 focus:bg-white transition-colors">
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400"><i data-lucide="lock" class="h-4 w-4"></i></div>
                        <input type="password" name="password" required placeholder="••••••••" class="block w-full pl-9 pr-3 py-3 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm bg-gray-50 focus:bg-white transition-colors">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirm Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400"><i data-lucide="lock" class="h-4 w-4"></i></div>
                        <input type="password" name="confirm_password" required placeholder="••••••••" class="block w-full pl-9 pr-3 py-3 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm bg-gray-50 focus:bg-white transition-colors">
                    </div>
                </div>
            </div>

            <button type="submit" class="w-full flex justify-center py-3.5 px-4 border border-transparent rounded-xl shadow-sm text-sm font-bold text-white bg-pup-maroon hover:bg-pup-maroonDark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-pup-maroon transition-all transform hover:-translate-y-0.5 mt-6">
                Register Account
            </button>
        </form>

        <div class="mt-8 text-center border-t border-gray-100 pt-6">
            <p class="text-sm text-gray-500">
                Already have an account? <a href="studentlogin.php" class="font-bold text-pup-maroon hover:underline">Sign in here</a>
            </p>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>