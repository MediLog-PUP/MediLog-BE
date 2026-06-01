<?php
session_start();
require '../db_connect.php';

$msg = '';
$error = '';
$success = false;

// Grab the token from the URL (GET) or from the submitted form (POST)
$token = $_GET['token'] ?? ($_POST['token'] ?? '');

if (empty($token)) {
    $error = "No password reset token provided. Please request a new recovery link.";
} else {
    // 1. Validate the token and check if it has expired
    $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires >= NOW() LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = "This password reset link is invalid or has expired. Please request a new one.";
    } else {
        // 2. Process the form submission
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];

            if (strlen($new_password) < 6) {
                $error = "Password must be at least 6 characters long.";
            } elseif ($new_password !== $confirm_password) {
                $error = "Passwords do not match.";
            } else {
                // 3. Hash the new password and update the database
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Clear the reset token so the link can't be used again
                $updateStmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
                
                if ($updateStmt->execute([$hashed_password, $user['id']])) {
                    $success = true;
                    $msg = "Your password has been successfully reset! You can now log in.";
                } else {
                    $error = "Something went wrong updating your password. Please try again.";
                }
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
    <title>Set New Password - MediLog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] }, colors: { pup: { maroon: '#880000', maroonDark: '#660000', gold: '#F1B500', goldLight: '#FDE68A' } } } } }
    </script>
</head>
<body class="font-sans antialiased text-gray-800 bg-gray-50 flex items-center justify-center min-h-screen relative overflow-hidden bg-[url('https://www.transparenttextures.com/patterns/cubes.png')]">

    <?php include '../global_loader.php'; ?>

    <div class="absolute top-0 w-full h-2 bg-gray-300"></div>

    <div class="w-full max-w-md p-8 sm:p-10 bg-white rounded-3xl shadow-xl border border-gray-100 z-10 mx-4">
        
        <?php if ($success): ?>
            <!-- Success State -->
            <div class="flex justify-center mb-6">
                <div class="bg-green-100 p-4 rounded-2xl border border-green-200">
                    <i data-lucide="check-circle-2" class="h-10 w-10 text-green-600"></i>
                </div>
            </div>
            <h2 class="text-2xl font-extrabold text-center text-gray-900 mb-2">Password Reset!</h2>
            <p class="text-center text-gray-500 text-sm mb-8"><?= htmlspecialchars($msg) ?></p>
            
            <a href="studentlogin.php" class="w-full flex justify-center py-3.5 px-4 border border-transparent rounded-xl shadow-sm text-sm font-bold text-white bg-gray-900 hover:bg-gray-800 focus:outline-none transition-all transform hover:-translate-y-0.5 mt-2">
                Go to Login
            </a>

        <?php elseif ($error && !$user): ?>
            <!-- Invalid Token State -->
            <div class="flex justify-center mb-6">
                <div class="bg-red-100 p-4 rounded-2xl border border-red-200">
                    <i data-lucide="shield-alert" class="h-10 w-10 text-red-600"></i>
                </div>
            </div>
            <h2 class="text-2xl font-extrabold text-center text-gray-900 mb-2">Link Expired</h2>
            <div class="bg-red-50 text-red-700 p-4 rounded-xl text-sm font-medium mb-6 text-center border border-red-100">
                <?= htmlspecialchars($error) ?>
            </div>
            
            <a href="forgot_password.php" class="w-full flex justify-center py-3.5 px-4 border border-gray-300 rounded-xl shadow-sm text-sm font-bold text-gray-700 bg-white hover:bg-gray-50 focus:outline-none transition-all mt-2">
                Request New Link
            </a>

        <?php else: ?>
            <!-- Reset Password Form -->
            <div class="flex justify-center mb-6">
                <div class="bg-gray-100 p-4 rounded-2xl border border-gray-200">
                    <i data-lucide="lock-keyhole" class="h-10 w-10 text-gray-500"></i>
                </div>
            </div>
            
            <h2 class="text-2xl font-extrabold text-center text-gray-900 mb-2">Create New Password</h2>
            <p class="text-center text-gray-500 text-sm mb-8">Your new password must be different from previous used passwords.</p>

            <?php if($error): ?>
                <div class="bg-red-50 text-red-700 p-4 rounded-xl text-sm font-semibold mb-6 flex items-center gap-2 border border-red-100">
                    <i data-lucide="alert-circle" class="h-5 w-5"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form action="reset_password.php" method="POST" class="space-y-5">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">New Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400">
                            <i data-lucide="lock" class="h-5 w-5"></i>
                        </div>
                        <input type="password" name="new_password" required minlength="6" placeholder="Enter new password" class="block w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-gray-900 focus:border-gray-900 sm:text-sm bg-gray-50 focus:bg-white transition-colors">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirm Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400">
                            <i data-lucide="lock-keyhole" class="h-5 w-5"></i>
                        </div>
                        <input type="password" name="confirm_password" required minlength="6" placeholder="Re-enter new password" class="block w-full pl-11 pr-4 py-3 border border-gray-300 rounded-xl focus:ring-gray-900 focus:border-gray-900 sm:text-sm bg-gray-50 focus:bg-white transition-colors">
                    </div>
                </div>

                <button type="submit" class="w-full flex justify-center py-3.5 px-4 border border-transparent rounded-xl shadow-sm text-sm font-bold text-white bg-gray-900 hover:bg-gray-800 focus:outline-none transition-all transform hover:-translate-y-0.5 mt-4">
                    Reset Password
                </button>
            </form>
        <?php endif; ?>

    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>