<?php
session_start();

header("Cache-Control: no-cache, no-store, must-revalidate"); 
header("Pragma: no-cache"); 
header("Expires: 0"); 

if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'faculty', 'super_admin', 'dentist'])) {
    if ($_SESSION['role'] === 'dentist') {
        header("Location: ../dentist/dentist_dashboard.php");
    } else {
        header("Location: ../admin/admin_dashboard.php");
    }
    exit();
}

require '../db_connect.php'; 
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_number = trim($_POST['id_number']);
    $password = $_POST['password'];

    if (!empty($id_number) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id_number = ? AND role IN ('admin', 'faculty', 'super_admin', 'dentist') LIMIT 1");
        $stmt->execute([$id_number]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            
            if ($user['role'] === 'dentist') {
                header("Location: ../dentist/dentist_dashboard.php");
            } else {
                header("Location: ../admin/admin_dashboard.php");
            }
            exit();
        } else {
            $error_msg = "Invalid Employee ID or Password.";
        }
    } else {
        $error_msg = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login - MediLog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = { 
            theme: { 
                extend: { 
                    fontFamily: { sans: ['Inter', 'sans-serif'] }, 
                    colors: { pup: { maroon: '#880000', maroonDark: '#660000', gold: '#F1B500' } },
                    animation: {
                        'blob': 'blob 7s infinite',
                        'gradient': 'gradient 15s ease infinite',
                    },
                    keyframes: {
                        blob: {
                            '0%': { transform: 'translate(0px, 0px) scale(1)' },
                            '33%': { transform: 'translate(30px, -50px) scale(1.1)' },
                            '66%': { transform: 'translate(-20px, 20px) scale(0.9)' },
                            '100%': { transform: 'translate(0px, 0px) scale(1)' },
                        },
                        gradient: {
                            '0%': { backgroundPosition: '0% 50%' },
                            '50%': { backgroundPosition: '100% 50%' },
                            '100%': { backgroundPosition: '0% 50%' },
                        }
                    }
                } 
            } 
        }
    </script>
    <style>
        .pattern-move {
            background-image: radial-gradient(rgba(15, 23, 42, 0.05) 1px, transparent 1px);
            background-size: 24px 24px;
            animation: patternMove 20s linear infinite;
        }
        @keyframes patternMove {
            0% { background-position: 0 0; }
            100% { background-position: 48px 48px; }
        }
    </style>
</head>
<body class="font-sans antialiased bg-gray-50 text-gray-900 min-h-screen flex">

    <div class="hidden lg:flex w-1/2 bg-gradient-to-br from-slate-800 via-gray-900 to-black animate-gradient bg-[length:400%_400%] p-12 flex-col justify-between relative overflow-hidden text-white shadow-[inset_-10px_0_30px_rgba(0,0,0,0.5)]">
        
        <div class="absolute top-0 right-0 -mt-20 -mr-20 w-96 h-96 bg-blue-500 opacity-20 rounded-full blur-3xl animate-blob"></div>
        <div class="absolute bottom-0 left-0 -mb-20 -ml-20 w-80 h-80 bg-pup-maroon opacity-30 rounded-full blur-3xl animate-blob" style="animation-delay: 2s;"></div>
        <div class="absolute top-1/2 left-1/4 transform -translate-y-1/2 w-72 h-72 bg-purple-500 opacity-20 rounded-full blur-3xl animate-blob" style="animation-delay: 4s;"></div>

        <div class="relative z-10 flex items-center gap-3">
            <img src="../img/ml.png" alt="MediLog Logo" class="h-10 w-auto object-contain">
            <span class="font-bold text-2xl tracking-tight drop-shadow-md">MediLog System</span>
        </div>

        <div class="relative z-10 max-w-md">
            <h1 class="text-4xl md:text-5xl font-extrabold mb-6 leading-tight drop-shadow-md">Clinic Staff <br>Portal</h1>
            <p class="text-gray-300 text-lg mb-8 leading-relaxed font-medium">Manage appointments, process medical clearances, and oversee clinic inventory securely.</p>
        </div>

        <div class="relative z-10 text-sm text-gray-400 font-medium">
            &copy; <?= date('Y') ?> MediLog System. All rights reserved.
        </div>
    </div>

    <div class="w-full lg:w-1/2 flex flex-col justify-center px-8 sm:px-16 md:px-24 py-12 relative bg-gray-50/90 pattern-move">
        
        <div class="absolute inset-0 bg-gradient-to-b from-white via-transparent to-gray-50/90 pointer-events-none"></div>

        <a href="../index.php" class="absolute top-8 left-8 sm:left-12 inline-flex items-center gap-2 text-sm font-bold text-gray-500 hover:text-gray-900 transition-colors group z-10">
            <div class="bg-white p-1.5 rounded-lg border border-gray-200 shadow-sm group-hover:border-gray-900 group-hover:bg-gray-100 transition-all">
                <i data-lucide="arrow-left" class="h-4 w-4 transition-transform group-hover:-translate-x-0.5"></i>
            </div>
            Back to Home
        </a>

        <div class="max-w-md w-full mx-auto mt-12 lg:mt-0 relative z-10 bg-white/70 backdrop-blur-md p-8 sm:p-10 rounded-3xl shadow-xl border border-white/60">
            <div class="lg:hidden flex items-center gap-3 mb-8">
                <img src="../your-logo-here.png" alt="MediLog Logo" class="h-10 w-auto object-contain">
                <span class="font-bold text-2xl tracking-tight text-gray-900">MediLog Staff</span>
            </div>

            <div class="mb-8">
                <h2 class="text-3xl font-extrabold text-gray-900 tracking-tight">Staff Sign In</h2>
                <p class="text-gray-500 mt-2 font-medium">Please enter your employee credentials.</p>
            </div>

            <?php if($error_msg): ?>
                <div class="bg-red-50 text-red-700 p-4 rounded-xl text-sm font-bold border border-red-200 flex items-center gap-3 mb-6 animate-pulse">
                    <i data-lucide="alert-circle" class="h-5 w-5 flex-shrink-0"></i> <?= htmlspecialchars($error_msg) ?>
                </div>
            <?php endif; ?>

            <form action="facultylogin.php" method="POST" class="space-y-5">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Employee ID Number</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i data-lucide="shield-check" class="h-5 w-5 text-gray-400"></i>
                        </div>
                        <input type="text" name="id_number" required placeholder="e.g. EMP-0001" class="block w-full pl-11 pr-4 py-3.5 bg-white border border-gray-200 rounded-xl text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent transition-all">
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-bold text-gray-700">Password</label>
                        <a href="forgot_password.php" class="text-sm font-bold text-gray-600 hover:text-gray-900 transition-colors">Forgot Password?</a>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i data-lucide="lock" class="h-5 w-5 text-gray-400"></i>
                        </div>
                        <input type="password" name="password" required placeholder="••••••••" class="block w-full pl-11 pr-4 py-3.5 bg-white border border-gray-200 rounded-xl text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent transition-all">
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full flex justify-center py-3.5 px-4 border border-transparent rounded-xl shadow-md text-sm font-bold text-white bg-gray-900 hover:bg-black focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 transition-all transform hover:-translate-y-0.5">
                        Sign In to Dashboard
                    </button>
                </div>
            </form>
            
            <div class="mt-8 pt-6 border-t border-gray-200 flex items-start gap-3">
                <div class="bg-yellow-50 p-2 rounded-lg text-yellow-600 flex-shrink-0"><i data-lucide="info" class="h-5 w-5"></i></div>
                <p class="text-xs text-gray-500 font-medium leading-relaxed">
                    Faculty and Admin accounts are securely provisioned by the Super Admin. If you need an account, please contact the clinic administrator.
                </p>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>