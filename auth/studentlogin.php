<?php
session_start();

header("Cache-Control: no-cache, no-store, must-revalidate"); 
header("Pragma: no-cache"); 
header("Expires: 0"); 

if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
    header("Location: ../student/student_dashboard.php");
    exit();
}

require '../db_connect.php'; 
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_number = trim($_POST['id_number']);
    $password = $_POST['password'];

    if (!empty($id_number) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id_number = ? AND role = 'student' LIMIT 1");
        $stmt->execute([$id_number]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            header("Location: ../student/student_dashboard.php");
            exit();
        } else {
            $error_msg = "Invalid Student ID or Password.";
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
    <title>Student Login - MediLog</title>
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
            background-image: radial-gradient(rgba(136, 0, 0, 0.1) 1px, transparent 1px); /* Faint maroon dots */
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

    <div class="hidden lg:flex w-1/2 bg-gradient-to-br from-pup-maroonDark via-pup-maroon to-red-900 animate-gradient bg-[length:400%_400%] p-12 flex-col justify-between relative overflow-hidden text-white shadow-[inset_-10px_0_30px_rgba(0,0,0,0.1)]">
        
        <div class="absolute top-0 right-0 -mt-20 -mr-20 w-96 h-96 bg-white opacity-10 rounded-full blur-3xl animate-blob"></div>
        <div class="absolute bottom-0 left-0 -mb-20 -ml-20 w-80 h-80 bg-pup-gold opacity-20 rounded-full blur-3xl animate-blob" style="animation-delay: 2s;"></div>
        <div class="absolute top-1/2 left-1/4 transform -translate-y-1/2 w-72 h-72 bg-red-400 opacity-20 rounded-full blur-3xl animate-blob" style="animation-delay: 4s;"></div>

        <div class="relative z-10 flex items-center gap-3">
            <img src="../img/ml.png" alt="MediLog Logo" class="h-10 w-auto object-contain">
            <span class="font-bold text-2xl tracking-tight drop-shadow-md">MediLog Clinic</span>
        </div>

        <div class="relative z-10 max-w-md">
            <h1 class="text-4xl md:text-5xl font-extrabold mb-6 leading-tight drop-shadow-md">Student Health <br>Portal</h1>
            <p class="text-red-100/90 text-lg mb-8 leading-relaxed font-medium">Book appointments, request medical clearances, and securely access your health records anytime, anywhere.</p>
        </div>

        <div class="relative z-10 text-sm text-red-200/70 font-medium">
            &copy; <?= date('Y') ?> MediLog System. All rights reserved.
        </div>
    </div>

    <div class="w-full lg:w-1/2 flex flex-col justify-center px-8 sm:px-16 md:px-24 py-12 relative bg-gray-50/80 pattern-move">
        
        <div class="absolute inset-0 bg-gradient-to-b from-white via-transparent to-gray-50/90 pointer-events-none"></div>

        <a href="../index.php" class="absolute top-8 left-8 sm:left-12 inline-flex items-center gap-2 text-sm font-bold text-gray-500 hover:text-pup-maroon transition-colors group z-10">
            <div class="bg-white p-1.5 rounded-lg border border-gray-200 shadow-sm group-hover:border-pup-maroon group-hover:bg-red-50 transition-all">
                <i data-lucide="arrow-left" class="h-4 w-4 transition-transform group-hover:-translate-x-0.5"></i>
            </div>
            Back to Home
        </a>

        <div class="max-w-md w-full mx-auto mt-12 lg:mt-0 relative z-10 bg-white/70 backdrop-blur-md p-8 sm:p-10 rounded-3xl shadow-xl border border-white/60">
            <div class="lg:hidden flex items-center gap-3 mb-8">
                <img src="../your-logo-here.png" alt="MediLog Logo" class="h-10 w-auto object-contain">
                <span class="font-bold text-2xl tracking-tight text-gray-900">MediLog</span>
            </div>

            <div class="mb-8">
                <h2 class="text-3xl font-extrabold text-gray-900 tracking-tight">Welcome Back! </h2>
                <p class="text-gray-500 mt-2 font-medium">Please sign in to your student account.</p>
            </div>

            <?php if($error_msg): ?>
                <div class="bg-red-50 text-red-700 p-4 rounded-xl text-sm font-bold border border-red-200 flex items-center gap-3 mb-6 animate-pulse">
                    <i data-lucide="alert-circle" class="h-5 w-5 flex-shrink-0"></i> <?= htmlspecialchars($error_msg) ?>
                </div>
            <?php endif; ?>

            <form action="studentlogin.php" method="POST" class="space-y-5">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Student ID Number</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i data-lucide="user" class="h-5 w-5 text-gray-400"></i>
                        </div>
                        <input type="text" name="id_number" required placeholder="e.g. 2021-00001-TG-0" class="block w-full pl-11 pr-4 py-3.5 bg-white border border-gray-200 rounded-xl text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-pup-maroon focus:border-transparent transition-all">
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-bold text-gray-700">Password</label>
                        <a href="forgot_password.php" class="text-sm font-bold text-pup-maroon hover:text-pup-maroonDark transition-colors">Forgot Password?</a>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i data-lucide="lock" class="h-5 w-5 text-gray-400"></i>
                        </div>
                        <input type="password" name="password" required placeholder="••••••••" class="block w-full pl-11 pr-4 py-3.5 bg-white border border-gray-200 rounded-xl text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-pup-maroon focus:border-transparent transition-all">
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full flex justify-center py-3.5 px-4 border border-transparent rounded-xl shadow-md text-sm font-bold text-white bg-pup-maroon hover:bg-pup-maroonDark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-pup-maroon transition-all transform hover:-translate-y-0.5">
                        Sign In to Portal
                    </button>
                </div>
            </form>

            <p class="mt-8 text-center text-sm text-gray-600 font-medium">
                Don't have an account? <a href="studentregister.php" class="font-bold text-pup-maroon hover:text-pup-maroonDark transition-colors">Register here</a>
            </p>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>