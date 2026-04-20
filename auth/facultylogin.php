<?php
session_start();

// --- BROWSER BACK BUTTON FIX ---
// Prevents the browser from caching the login page.
header("Cache-Control: no-cache, no-store, must-revalidate"); 
header("Pragma: no-cache"); 
header("Expires: 0"); 

// If already logged in as staff, instantly redirect to respective dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin') {
        header("Location: ../admin/admin_dashboard.php");
        exit();
    } elseif ($_SESSION['role'] === 'dentist') {
        header("Location: ../dentist/dentist_dashboard.php");
        exit();
    }
}

require '../db_connect.php'; 
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!empty($email) && !empty($password)) {
        // Staff can be admin, superadmin, or dentist
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role IN ('admin', 'superadmin', 'dentist') LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Assuming password_hash() is used.
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
            $error_msg = "Invalid Staff Email or Password.";
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
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] }, colors: { pup: { maroon: '#880000', maroonDark: '#660000', gold: '#F1B500' } }, colors: { brand: { 900: '#0f172a', 800: '#1e293b', 600: '#475569', blue: '#2563eb' } } } } }
    </script>
</head>
<body class="font-sans antialiased bg-white text-gray-900 min-h-screen flex">

    <div class="hidden lg:flex w-1/2 bg-brand-900 p-12 flex-col justify-between relative overflow-hidden text-white">
        <div class="absolute top-0 right-0 -mt-20 -mr-20 w-96 h-96 bg-brand-blue opacity-20 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 left-0 -mb-20 -ml-20 w-80 h-80 bg-pup-maroon opacity-20 rounded-full blur-3xl"></div>

        <div class="relative z-10 flex items-center gap-3">
            <div class="bg-white/10 p-2.5 rounded-xl backdrop-blur-sm border border-white/20"><i data-lucide="shield-check" class="h-8 w-8 text-blue-400"></i></div>
            <span class="font-bold text-2xl tracking-tight">MediLog System</span>
        </div>

        <div class="relative z-10 max-w-md">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/20 text-blue-300 text-xs font-bold uppercase tracking-wider mb-6 border border-blue-500/30">
                Authorized Personnel Only
            </div>
            <h1 class="text-4xl md:text-5xl font-extrabold mb-6 leading-tight">Faculty & Staff <br>Portal</h1>
            <p class="text-gray-300 text-lg mb-8 leading-relaxed">Manage clinic operations, process medical clearances, update inventory, and handle student health records.</p>
            
            <div class="grid grid-cols-2 gap-4 mt-8">
                <div class="bg-white/5 p-4 rounded-xl border border-white/10">
                    <i data-lucide="users" class="h-6 w-6 text-blue-400 mb-2"></i>
                    <div class="font-bold text-sm">Clinic Admins</div>
                </div>
                <div class="bg-white/5 p-4 rounded-xl border border-white/10">
                    <i data-lucide="stethoscope" class="h-6 w-6 text-blue-400 mb-2"></i>
                    <div class="font-bold text-sm">Dental Faculty</div>
                </div>
            </div>
        </div>

        <div class="relative z-10 text-sm text-gray-500 font-medium">
            &copy; <?= date('Y') ?> MediLog System. Secure Access.
        </div>
    </div>

    <div class="w-full lg:w-1/2 flex flex-col justify-center px-8 sm:px-16 md:px-24 py-12 relative bg-gray-50/50">
        
        <a href="../index.php" class="absolute top-8 left-8 sm:left-12 inline-flex items-center gap-2 text-sm font-bold text-gray-500 hover:text-brand-blue transition-colors group">
            <div class="bg-white p-1.5 rounded-lg border border-gray-200 shadow-sm group-hover:border-brand-blue group-hover:bg-blue-50 transition-all">
                <i data-lucide="arrow-left" class="h-4 w-4 transition-transform group-hover:-translate-x-0.5"></i>
            </div>
            Back to Home
        </a>

        <div class="max-w-md w-full mx-auto mt-12 lg:mt-0">
            <div class="lg:hidden flex items-center gap-3 mb-8">
                <div class="bg-brand-900 p-2.5 rounded-xl shadow-lg"><i data-lucide="shield-check" class="h-6 w-6 text-blue-400"></i></div>
                <span class="font-bold text-2xl tracking-tight text-gray-900">MediLog Staff</span>
            </div>

            <div class="mb-8">
                <h2 class="text-3xl font-extrabold text-gray-900 tracking-tight">Staff Sign In</h2>
                <p class="text-gray-500 mt-2 font-medium">Secure access for administrative and medical faculty.</p>
            </div>

            <?php if($error_msg): ?>
                <div class="bg-red-50 text-red-700 p-4 rounded-xl text-sm font-bold border border-red-200 flex items-center gap-3 mb-6 animate-pulse">
                    <i data-lucide="alert-circle" class="h-5 w-5 flex-shrink-0"></i> <?= htmlspecialchars($error_msg) ?>
                </div>
            <?php endif; ?>

            <form action="facultylogin.php" method="POST" class="space-y-5">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Staff Email Address</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i data-lucide="mail" class="h-5 w-5 text-gray-400"></i>
                        </div>
                        <input type="email" name="email" required placeholder="admin@medilog.edu" class="block w-full pl-11 pr-4 py-3.5 bg-white border border-gray-200 rounded-xl text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-blue focus:border-transparent transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i data-lucide="lock" class="h-5 w-5 text-gray-400"></i>
                        </div>
                        <input type="password" name="password" required placeholder="••••••••" class="block w-full pl-11 pr-4 py-3.5 bg-white border border-gray-200 rounded-xl text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-blue focus:border-transparent transition-all">
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" class="w-full flex items-center justify-center gap-2 py-3.5 px-4 border border-transparent rounded-xl shadow-md text-sm font-bold text-white bg-brand-900 hover:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-900 transition-all transform hover:-translate-y-0.5">
                        <i data-lucide="log-in" class="h-4 w-4"></i> Secure Login
                    </button>
                </div>
            </form>

            <div class="mt-8 p-4 bg-blue-50 border border-blue-100 rounded-xl flex gap-3 text-sm text-blue-800">
                <i data-lucide="info" class="h-5 w-5 flex-shrink-0 text-blue-600"></i>
                <p class="font-medium">New faculty members must request account creation from the Super Admin.</p>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>