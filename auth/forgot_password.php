<?php
session_start();
require '../db_connect.php';

// Include PHPMailer library files
// Ensure you uploaded the PHPMailer 'src' folder into 'auth/PHPMailer/src/'
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$msg = '';
$error = '';

// Auto-add reset token columns to users table if they don't exist
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) DEFAULT NULL, ADD COLUMN reset_expires DATETIME DEFAULT NULL");
} catch (PDOException $e) {
    // Columns already exist
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $identifier = trim($_POST['identifier']);

    // Check if user exists by email or ID number
    $stmt = $pdo->prepare("SELECT id, email, full_name FROM users WHERE email = ? OR id_number = ? LIMIT 1");
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    if ($user) {
        // 1. Generate a secure random token
        $token = bin2hex(random_bytes(32));

        // 2. Save token to database (Using MySQL's internal clock to prevent timezone mismatch)
        $updateStmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?");
        $updateStmt->execute([$token, $user['id']]);

        // 3. Create the reset link dynamically based on your current domain
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $domain = $_SERVER['HTTP_HOST'];
        $reset_link = $protocol . "://" . $domain . "/Medllog5/auth/reset_password.php?token=" . $token;

        // 4. Send Email via PHPMailer & Gmail SMTP
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            
            // =========================================================
            // PUT YOUR GMAIL AND 16-CHARACTER APP PASSWORD HERE:
            // =========================================================
            $mail->Username   = 'godoyjp443@gmail.com'; // <--- Change this
            $mail->Password   = 'mjoxboetuzjivcnb'; // <--- Change this (No spaces)
            // =========================================================
            
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Recipients
            $mail->setFrom('godoyjp443@gmail.com', 'MediLog Clinic'); // <--- Change this
            $mail->addAddress($user['email'], $user['full_name']);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request - MediLog';
            $mail->Body    = "Hello " . htmlspecialchars($user['full_name']) . ",<br><br>
                              You requested a password reset. Click the link below to set a new password. This link expires in 1 hour.<br><br>
                              <a href='" . $reset_link . "'>Reset Password</a><br><br>
                              If you did not request this, please ignore this email.";

            $mail->send();
            $msg = "A recovery link has been sent to your registered email address.";
        } catch (Exception $e) {
            $error = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
    } else {
        // Security best practice: Don't reveal if the email exists or not
        $msg = "If the email or ID exists, a recovery link has been sent.";
    }
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
        <?php if($error): ?>
            <div class="bg-red-50 text-red-700 p-4 rounded-xl text-sm font-semibold mb-6 flex items-center gap-2 border border-red-100">
                <i data-lucide="alert-circle" class="h-5 w-5"></i> <?= htmlspecialchars($error) ?>
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