<?php
session_start();
require '../db_connect.php';

// --- MAGIC UPGRADE BACKDOOR ---
// To create your first Super Admin and modify your DB, go to: admin/super_admin_users.php?upgrade_me=1
if (isset($_GET['upgrade_me']) && isset($_SESSION['user_id'])) {
    try {
        // Safely alter the ENUM to include super_admin (adjust as necessary if your DB uses VARCHAR)
        $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('student', 'faculty', 'admin', 'super_admin') DEFAULT 'student'");
    } catch (PDOException $e) {
        // Ignore if already modified or unsupported
    }
    
    // Promote current user
    $pdo->prepare("UPDATE users SET role = 'super_admin' WHERE id = ?")->execute([$_SESSION['user_id']]);
    $_SESSION['role'] = 'super_admin';
    header("Location: super_admin_users.php");
    exit();
}

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/facultylogin.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// FETCH LATEST USER DATA FIRST to sync the role (Fixes issues when edited directly via phpMyAdmin)
$stmt = $pdo->prepare("SELECT full_name, profile_pic, role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($user) {
    $_SESSION['role'] = $user['role']; // Force session to update to the newest DB role
}

// ALLOW admin and super_admin so the page loads and shows the UI lock screen instead of redirecting!
if (!in_array($_SESSION['role'], ['admin', 'faculty', 'super_admin'])) {
    header("Location: ../auth/facultylogin.php");
    exit();
}

$is_super_admin = ($_SESSION['role'] === 'super_admin');
$success_msg = '';
$error_msg = '';

// ONLY process form submissions and fetch admin lists IF the user is a true super admin
if ($is_super_admin) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_admin') {
        $full_name = trim($_POST['full_name']);
        $id_number = trim($_POST['id_number']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $role = $_POST['role'];

        $stmt = $pdo->prepare("SELECT id FROM users WHERE id_number = ?");
        $stmt->execute([$id_number]);
        if ($stmt->fetch()) {
            $error_msg = "Employee ID already exists in the system.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare("INSERT INTO users (full_name, id_number, email, password, role) VALUES (?, ?, ?, ?, ?)");
            if ($insert->execute([$full_name, $id_number, $email, $hashed_password, $role])) {
                $success_msg = "New " . str_replace('_', ' ', $role) . " account created successfully!";
            } else {
                $error_msg = "Failed to create account.";
            }
        }
    }

    $adminsStmt = $pdo->query("SELECT id, full_name, id_number, email, role FROM users WHERE role IN ('admin', 'faculty', 'super_admin') ORDER BY role DESC, full_name ASC");
    $admins = $adminsStmt->fetchAll(PDO::FETCH_ASSOC);
}

$profile_pic = 'https://ui-avatars.com/api/?name=' . urlencode($user['full_name']) . '&background=880000&color=fff';
if (!empty($user['profile_pic']) && $user['profile_pic'] !== 'default.png') {
    if (strpos($user['profile_pic'], 'data:image') === 0) {
        $profile_pic = $user['profile_pic'];
    } else {
        $profile_pic = '../uploads/profiles/' . htmlspecialchars($user['profile_pic']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management - MediLog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style> .no-scrollbar::-webkit-scrollbar { display: none; } .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; } </style>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] }, colors: { pup: { maroon: '#880000', gold: '#F1B500' } } } } }
    </script>
</head>
<body class="font-sans antialiased text-gray-800 bg-gray-50 flex h-screen overflow-hidden">

    <?php include '../global_loader.php'; ?>

    <!-- DESKTOP SIDEBAR -->
    <aside class="hidden md:flex flex-col w-64 bg-gray-900 text-white h-full shadow-xl z-20 flex-shrink-0">
        <div class="p-6 flex items-center gap-3 border-b border-gray-800">
            <div class="bg-pup-gold text-gray-900 p-2 rounded-lg"><i data-lucide="shield-plus" class="h-6 w-6"></i></div>
            <span class="font-bold text-xl tracking-tight text-white">MediLog Admin</span>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto no-scrollbar">
            <a href="admin_dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="layout-dashboard" class="h-5 w-5"></i> Overview</a>
            <a href="medicine_inventory.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="pill" class="h-5 w-5"></i> Inventory</a>
            <a href="patient_records.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="users" class="h-5 w-5"></i> Patient Records</a>
            <a href="admin_treatment_records.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="clipboard-list" class="h-5 w-5"></i> Treatment Records</a>
            <a href="admin_appointments.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="calendar" class="h-5 w-5"></i> Appointments</a>
            <a href="admin_clearance.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="file-check-2" class="h-5 w-5"></i> Clearances</a>
            <a href="admin_inquiries.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="message-square" class="h-5 w-5"></i> Inquiries</a>
            <a href="admin_profile.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="user-cog" class="h-5 w-5"></i> Profile</a>
            
            <a href="super_admin_users.php" class="flex items-center gap-3 px-4 py-3 bg-pup-maroon text-white rounded-xl font-medium transition-colors shadow-sm"><i data-lucide="shield-alert" class="h-5 w-5"></i> Admin Management</a>
        </nav>
        <div class="p-4 border-t border-gray-800">
            <a href="../auth/logout.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-red-900/50 hover:text-red-400 rounded-xl font-medium transition-colors w-full"><i data-lucide="log-out" class="h-5 w-5"></i> Sign Out</a>
        </div>
    </aside>

    <main class="flex-1 flex flex-col h-full overflow-hidden bg-gray-50 relative">
        <header class="bg-white border-b border-gray-200 px-8 py-4 flex items-center justify-between shadow-sm z-10">
            <h1 class="text-2xl font-bold text-gray-900">Admin Account Management</h1>
            <div class="flex items-center gap-4">
                <?php if($is_super_admin): ?>
                    <span class="text-sm font-bold text-white bg-purple-600 px-3 py-1 rounded-full hidden sm:inline-block"><i data-lucide="shield-alert" class="h-4 w-4 inline mr-1"></i> Super Admin</span>
                <?php else: ?>
                    <span class="text-sm font-bold text-gray-700 bg-gray-100 px-3 py-1 rounded-full hidden sm:inline-block"><i data-lucide="shield-check" class="h-4 w-4 inline mr-1 text-green-600"></i> Clinic Admin</span>
                <?php endif; ?>
                <p class="text-sm font-semibold text-gray-900 hidden sm:block"><?= htmlspecialchars($user['full_name']) ?></p>
                <img src="<?= $profile_pic ?>" alt="Profile" class="h-10 w-10 rounded-full border-2 border-gray-200 object-cover">
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 sm:p-8 pb-24 md:pb-8">
            <div class="max-w-6xl mx-auto space-y-6">
                
                <?php if(!$is_super_admin): ?>
                    <!-- ACCESS DENIED MESSAGE FOR REGULAR ADMINS -->
                    <div class="bg-white rounded-2xl border border-red-200 shadow-sm overflow-hidden flex flex-col items-center justify-center p-12 text-center mt-10">
                        <div class="bg-red-50 p-6 rounded-full mb-4">
                            <i data-lucide="lock" class="h-16 w-16 text-red-500"></i>
                        </div>
                        <h2 class="text-2xl font-extrabold text-gray-900 mb-2">Access Restricted</h2>
                        <p class="text-gray-500 max-w-md">Only Super Admins may view or modify system administrator accounts. If you require access, please contact the system administrator.</p>
                        <a href="admin_dashboard.php" class="mt-6 inline-flex items-center gap-2 bg-gray-900 text-white px-6 py-3 rounded-xl font-bold hover:bg-gray-800 transition-colors">
                            <i data-lucide="arrow-left" class="h-5 w-5"></i> Return to Dashboard
                        </a>
                    </div>
                <?php else: ?>
                    <!-- FULL MANAGEMENT UI FOR SUPER ADMINS -->
                    <?php if($success_msg): ?>
                        <div class="bg-green-50 text-green-700 p-4 rounded-xl text-sm font-medium border border-green-200 flex items-center gap-2">
                            <i data-lucide="check-circle-2" class="h-5 w-5"></i> <?= htmlspecialchars($success_msg) ?>
                        </div>
                    <?php endif; ?>
                    <?php if($error_msg): ?>
                        <div class="bg-red-50 text-red-700 p-4 rounded-xl text-sm font-bold border border-red-200 flex items-center gap-2">
                            <i data-lucide="alert-circle" class="h-5 w-5"></i> <?= htmlspecialchars($error_msg) ?>
                        </div>
                    <?php endif; ?>

                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                        <div class="p-4 sm:p-6 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                            <h2 class="text-lg font-bold text-gray-900">Authorized Personnel</h2>
                            <button onclick="document.getElementById('addAdminModal').classList.remove('hidden')" class="bg-pup-maroon hover:bg-red-900 text-white px-4 py-2 rounded-xl text-sm font-semibold flex items-center gap-2 transition-colors"><i data-lucide="user-plus" class="h-4 w-4"></i> <span class="hidden sm:inline">Create Account</span></button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse min-w-[600px]">
                                <thead>
                                    <tr class="bg-white text-gray-500 text-xs uppercase tracking-wider border-b border-gray-200">
                                        <th class="p-4 font-semibold">Full Name</th>
                                        <th class="p-4 font-semibold">Employee ID</th>
                                        <th class="p-4 font-semibold">Email</th>
                                        <th class="p-4 font-semibold">Role Level</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-sm">
                                    <?php foreach($admins as $a): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="p-4 text-gray-900 font-bold"><?= htmlspecialchars($a['full_name']) ?></td>
                                            <td class="p-4 text-gray-600"><?= htmlspecialchars($a['id_number']) ?></td>
                                            <td class="p-4 text-gray-600"><?= htmlspecialchars($a['email']) ?></td>
                                            <td class="p-4">
                                                <?php if($a['role'] === 'super_admin'): ?>
                                                    <span class="bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-xs font-bold">Super Admin</span>
                                                <?php else: ?>
                                                    <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-bold">Clinic Admin</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- NEW MOBILE BOTTOM BAR -->
        <nav class="md:hidden fixed bottom-0 left-0 w-full bg-white border-t border-gray-200 shadow-[0_-4px_10px_rgba(0,0,0,0.05)] z-40 pb-safe">
            <div class="flex items-center overflow-x-auto no-scrollbar snap-x snap-mandatory">
                <a href="admin_dashboard.php" class="snap-center flex-1 min-w-[70px] flex flex-col items-center justify-center p-3 text-gray-400 hover:text-pup-maroon transition-colors relative">
                    <i data-lucide="layout-dashboard" class="h-6 w-6 mb-1"></i>
                    <span class="text-[10px] font-bold">Home</span>
                </a>
                <a href="patient_records.php" class="snap-center flex-1 min-w-[70px] flex flex-col items-center justify-center p-3 text-gray-400 hover:text-pup-maroon transition-colors relative">
                    <i data-lucide="users" class="h-6 w-6 mb-1"></i>
                    <span class="text-[10px] font-bold">Patients</span>
                </a>
                <a href="admin_appointments.php" class="snap-center flex-1 min-w-[70px] flex flex-col items-center justify-center p-3 text-gray-400 hover:text-pup-maroon transition-colors relative">
                    <i data-lucide="calendar" class="h-6 w-6 mb-1"></i>
                    <span class="text-[10px] font-bold">Appts</span>
                </a>
                <a href="admin_inquiries.php" class="snap-center flex-1 min-w-[70px] flex flex-col items-center justify-center p-3 text-gray-400 hover:text-pup-maroon transition-colors relative">
                    <i data-lucide="message-square" class="h-6 w-6 mb-1"></i>
                    <span class="text-[10px] font-bold">Chat</span>
                </a>
                <a href="admin_profile.php" class="snap-center flex-1 min-w-[70px] flex flex-col items-center justify-center p-3 text-gray-400 hover:text-pup-maroon transition-colors relative">
                    <i data-lucide="user-cog" class="h-6 w-6 mb-1"></i>
                    <span class="text-[10px] font-bold">Profile</span>
                </a>
                
                <a href="super_admin_users.php" class="snap-center flex-1 min-w-[70px] flex flex-col items-center justify-center p-3 text-pup-maroon transition-colors relative">
                    <i data-lucide="shield-alert" class="h-6 w-6 mb-1"></i>
                    <span class="text-[10px] font-bold">Admins</span>
                    <span class="absolute top-0 w-8 h-1 bg-pup-maroon rounded-b-md"></span>
                </a>
            </div>
        </nav>

    </main>

    <?php if($is_super_admin): ?>
    <!-- Add Admin Modal (Only renders for Super Admin) -->
    <div id="addAdminModal" class="fixed inset-0 z-50 hidden bg-gray-900 bg-opacity-75 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-md overflow-hidden shadow-2xl">
            <form action="super_admin_users.php" method="POST">
                <input type="hidden" name="action" value="add_admin">
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-gray-900">Create New Admin</h3>
                    <button type="button" onclick="document.getElementById('addAdminModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                        <input type="text" name="full_name" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Employee ID</label>
                        <input type="text" name="id_number" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Temporary Password</label>
                        <input type="text" name="password" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <select name="role" required class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                            <option value="admin">Clinic Admin</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                    </div>
                </div>
                <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('addAdminModal').classList.add('hidden')" class="px-4 py-2 bg-white border border-gray-300 rounded-xl text-sm font-bold text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-pup-maroon text-white rounded-xl text-sm font-bold hover:bg-red-900">Create Account</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>lucide.createIcons();</script>
</body>
</html>