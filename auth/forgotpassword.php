<?php
session_start();
require '../db_connect.php';

// Redirect if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'faculty') {
        header("Location: admin_dashboard.php");
        exit();
    } elseif ($_SESSION['role'] === 'student') {
        header("Location: student_dashboard.php");
        exit();
    }
}

$success_msg = '';
$error_msg = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = trim($_POST['user-id']);
    
    if (empty($user_id)) {
        $error_msg = "Please enter your ID Number or Email.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT email FROM users WHERE id_number = ? OR email = ?");
            $stmt->execute([$user_id, $user_id]);
            $user = $stmt->fetch();

            if ($user) {
                $success_msg = "A password reset link has been sent to " . htmlspecialchars($user['email']) . ". Please check your inbox.";
            } else {
                $error_msg = "We couldn't find an account associated with that ID or Email.";
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
    <title>Forgot Password - MediLog</title>
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
    </style>
</head>
<body class="font-sans antialiased text-gray-800 min-h-screen flex items-center justify-center relative bg-gray-50 overflow-hidden">

    <!-- Decorative Elements -->
    <div class="absolute top-[-20%] right-[-10%] w-[500px] h-[500px] bg-pup-gold rounded-full mix-blend-multiply filter blur-3xl opacity-20"></div>
    <div class="absolute bottom-[-20%] left-[-10%] w-[500px] h-[500px] bg-pup-maroon rounded-full mix-blend-multiply filter blur-3xl opacity-10"></div>

    <div class="w-full max-w-md bg-white/80 backdrop-blur-xl rounded-3xl shadow-2xl border border-white/50 p-8 sm:p-10 relative z-10 mx-4">
        
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center bg-white p-4 rounded-2xl mb-5 shadow-sm border border-gray-100">
                <i data-lucide="shield-question" class="h-10 w-10 text-pup-maroon"></i>
            </div>
            <h2 class="text-3xl font-extrabold text-gray-900 mb-2">Forgot Password?</h2>
            <p class="text-sm text-gray-500 leading-relaxed">
                Enter your registered Student/Employee ID or Email address. We'll send you secure instructions to reset your password.
            </p>
        </div>

        <?php if($error_msg): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 flex items-start gap-3 text-sm font-medium animate-pulse">
                <i data-lucide="alert-circle" class="h-5 w-5 flex-shrink-0 mt-0.5"></i>
                <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>

        <?php if($success_msg): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-4 rounded-xl mb-6 flex items-start gap-3 text-sm font-medium">
                <i data-lucide="check-circle-2" class="h-6 w-6 flex-shrink-0 text-green-600 mt-0.5"></i>
                <div>
                    <span class="block font-bold text-green-800 mb-1">Email Sent Successfully</span>
                    <?php echo htmlspecialchars($success_msg); ?>
                </div>
            </div>
        <?php else: ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-5">
                <div>
                    <label for="user-id" class="block text-sm font-medium text-gray-700 mb-1.5">ID Number or Email</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i data-lucide="mail" class="h-5 w-5 text-gray-400"></i>
                        </div>
                        <input type="text" id="user-id" name="user-id" class="block w-full pl-11 pr-4 py-3.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-pup-maroon focus:border-transparent sm:text-sm transition-all bg-white shadow-sm" placeholder="e.g. 2021-00000-MN-0" required>
                    </div>
                </div>

                <button type="submit" class="w-full flex justify-center items-center py-3.5 px-4 rounded-xl shadow-md text-sm font-bold text-white bg-pup-maroon hover:bg-pup-maroonDark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-pup-maroon transition-all transform hover:-translate-y-0.5 mt-2">
                    Send Reset Link <i data-lucide="send" class="ml-2 h-4 w-4"></i>
                </button>
            </form>
        <?php endif; ?>

        <div class="mt-8 text-center">
            <a href="../index.php" class="inline-flex items-center text-sm font-semibold text-gray-500 hover:text-pup-maroon transition-colors group">
                <i data-lucide="arrow-left" class="h-4 w-4 mr-2 group-hover:-translate-x-1 transition-transform"></i> Back to Login
            </a>
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
                    setTimeout(() => {
                        window.location.href = href;
                    }, 250); 
                });
            });
        });
    </script>
</body>
</html>