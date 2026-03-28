<?php
session_start();
// Dummy placeholder for forgot password logic.
// You would add mailer/token generation logic here.
$msg = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $msg = "If the email or ID exists, a recovery link has been sent.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - MediLog</title>
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

    <div class="absolute top-0 w-full h-2 bg-gray-300"></div>

    <div class="w-full max-w-md p-8 sm:p-10 bg-white rounded-3xl shadow-xl border border-gray-100 z-10 mx-4">
        <div class="flex justify-center mb-6">
            <div class="bg-gray-100 p-4 rounded-2xl border border-gray-200">
                <i data-lucide="key-round" class="h-10 w-10 text-gray-500"></i>
            </div>
        </div>
        
        <h2 class="text-2xl font-extrabold text-center text-gray-900 mb-2">Reset Password</h2>
        <p class="text-center text-gray-500 text-sm mb-8">Enter your registered email or ID number to receive a recovery link.</p>

        <?php if($msg): ?>
            <div class="bg-green-50 text-green-700 p-4 rounded-xl text-sm font-semibold mb-6 flex items-center gap-2 border border-green-100">
                <i data-lucide="info" class="h-5 w-5"></i> <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <form action="forgot_password.php" method="POST" class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Email or ID Number</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400">
                        <i data-lucide="at-sign" class="h-5 w-5"></i>
                    </div>
                    <input type="text" name="identifier" required placeholder="student@iskolar.edu.ph" class="block w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-gray-900 focus:border-gray-900 sm:text-sm bg-gray-50 focus:bg-white transition-colors">
                </div>
            </div>

            <button type="submit" class="w-full flex justify-center py-3.5 px-4 border border-transparent rounded-xl shadow-sm text-sm font-bold text-white bg-gray-900 hover:bg-gray-800 focus:outline-none transition-all transform hover:-translate-y-0.5 mt-2">
                Send Recovery Link
            </button>
        </form>

        <div class="mt-8 text-center border-t border-gray-100 pt-6">
            <a href="studentlogin.php" class="text-sm font-semibold text-gray-500 hover:text-gray-900 flex items-center justify-center gap-2 transition-colors">
                <i data-lucide="arrow-left" class="h-4 w-4"></i> Back to Login
            </a>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>