<?php
session_start();
require '../db_connect.php'; 

// Redirect to login if not authenticated as a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/studentlogin.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle Mark as Read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$user_id]);
    header("Location: student_notifications.php");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$profile_pic = 'https://ui-avatars.com/api/?name=' . urlencode($user['full_name']) . '&background=880000&color=fff';
if (!empty($user['profile_pic']) && $user['profile_pic'] !== 'default.png') {
    if (strpos($user['profile_pic'], 'data:image') === 0) {
        $profile_pic = $user['profile_pic'];
    } else {
        $profile_pic = '../uploads/profiles/' . htmlspecialchars($user['profile_pic']);
    }
}

// Fetch Notifications
$notifStmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 30");
$notifStmt->execute([$user_id]);
$notifications = $notifStmt->fetchAll(PDO::FETCH_ASSOC);

function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;
    $string = array('y' => 'year', 'm' => 'month', 'w' => 'week', 'd' => 'day', 'h' => 'hour', 'i' => 'minute', 's' => 'second');
    foreach ($string as $k => &$v) {
        if ($diff->$k) { $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : ''); } else { unset($string[$k]); }
    }
    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - MediLog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] }, colors: { pup: { maroon: '#880000', maroonDark: '#660000', gold: '#F1B500', goldLight: '#FDE68A' } } } } }
    </script>
    <style>
        body { opacity: 0; animation: fadeIn 0.4s ease-out forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .page-exit { animation: fadeOut 0.3s ease-in forwards !important; }
        @keyframes fadeOut { from { opacity: 1; transform: translateY(0); } to { opacity: 0; transform: translateY(-10px); } }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="font-sans antialiased text-gray-800 bg-gray-50 flex h-screen overflow-hidden">

    <?php include '../global_loader.php'; ?>

    <!-- Sidebar -->
    <aside class="hidden md:flex flex-col w-64 bg-gray-900 text-white h-full shadow-xl z-20 flex-shrink-0">
        <div class="p-6 flex items-center gap-3 border-b border-gray-800">
            <div class="bg-pup-maroon text-white p-2 rounded-lg"><i data-lucide="clipboard-heart" class="h-6 w-6 text-pup-gold"></i></div>
            <span class="font-bold text-xl tracking-tight text-white">MediLog</span>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <a href="student_dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="layout-dashboard" class="h-5 w-5"></i> Dashboard</a>
            <a href="book_appointment.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="calendar-plus" class="h-5 w-5"></i> Book Appointment</a>
            <a href="health_records.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="folder-clock" class="h-5 w-5"></i> Health Records</a>
            <a href="medical_clearance.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="file-check-2" class="h-5 w-5"></i> Medical Clearance</a>
            <a href="#" onclick="toggleChat(); return false;" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="message-square" class="h-5 w-5"></i> Messages</a>
            <a href="student_profile.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="user" class="h-5 w-5"></i> Profile</a>
        </nav>
        <div class="p-4 border-t border-gray-800">
            <button onclick="openLogoutModal()" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-red-900/50 hover:text-red-400 rounded-xl font-medium transition-colors w-full text-left"><i data-lucide="log-out" class="h-5 w-5"></i> Sign Out</button>
        </div>
    </aside>

    <!-- Mobile Bottom Navigation -->
    <nav class="md:hidden fixed bottom-0 left-0 w-full bg-white border-t border-gray-200 z-50 overflow-x-auto no-scrollbar shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
        <div class="flex items-center w-max px-2 min-w-full justify-between">
            <a href="student_dashboard.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="layout-dashboard" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Home</span></a>
            <a href="book_appointment.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="calendar-plus" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Book</span></a>
            <a href="health_records.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="folder-clock" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Records</span></a>
            <a href="medical_clearance.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="file-check-2" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Clearance</span></a>
            <a href="#" onclick="toggleChat(); return false;" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="message-square" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Messages</span></a>
            <a href="student_profile.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="user" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Profile</span></a>
        </div>
    </nav>

    <main class="flex-1 flex flex-col h-full overflow-hidden relative">
        <header class="bg-white border-b border-gray-200 px-4 md:px-8 py-4 flex items-center justify-between z-10 shadow-sm">
            <div class="flex items-center gap-3">
                <a href="student_dashboard.php" class="md:hidden text-gray-500 hover:text-pup-maroon"><i data-lucide="arrow-left" class="h-6 w-6"></i></a>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900">Notifications</h1>
            </div>
            <div class="flex items-center gap-3 sm:gap-4">
                <button onclick="openLogoutModal()" class="md:hidden text-gray-500 hover:text-red-600 transition-colors p-2 bg-gray-50 rounded-full border border-gray-200"><i data-lucide="log-out" class="h-5 w-5"></i></button>
                <div class="hidden md:flex items-center gap-3 pl-4 border-l border-gray-200">
                    <div class="text-right"><p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($user['full_name']) ?></p></div>
                    <img src="<?= $profile_pic ?>" alt="Profile" class="h-10 w-10 rounded-full border-2 border-gray-200 object-cover hidden md:block">
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 sm:p-8 pb-24 md:pb-8">
            <div class="max-w-4xl mx-auto space-y-4">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-lg font-bold text-gray-900">Recent Activity</h2>
                    <form method="POST">
                        <button type="submit" name="mark_read" class="text-sm font-semibold text-pup-maroon hover:underline flex items-center gap-1"><i data-lucide="check-check" class="h-4 w-4"></i> Mark all as read</button>
                    </form>
                </div>

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="divide-y divide-gray-100">
                        <?php if(count($notifications) > 0): ?>
                            <?php foreach($notifications as $notif): 
                                $message = htmlspecialchars($notif['message']);
                                
                                // Default values
                                $icon = 'bell-ring';
                                $iconColor = 'text-gray-600';
                                $bgColor = 'bg-gray-100';
                                $title = 'System Update';

                                // Determine notification type based on prefixes
                                if (strpos($message, '[Clearance]') !== false) {
                                    $icon = 'file-check-2'; $iconColor = 'text-green-600'; $bgColor = 'bg-green-50'; $title = 'Clearance Update';
                                    $message = str_replace('[Clearance] ', '', $message);
                                } elseif (strpos($message, '[Appointment]') !== false) {
                                    $icon = 'calendar'; $iconColor = 'text-yellow-600'; $bgColor = 'bg-yellow-50'; $title = 'Appointment Update';
                                    $message = str_replace('[Appointment] ', '', $message);
                                } elseif (strpos($message, '[Message]') !== false) {
                                    $icon = 'message-square'; $iconColor = 'text-purple-600'; $bgColor = 'bg-purple-50'; $title = 'New Message';
                                    $message = str_replace('[Message] ', '', $message);
                                }
                            ?>
                                <div class="p-5 <?= $notif['is_read'] ? 'bg-white' : 'bg-red-50/30 hover:bg-red-50 border-l-4 border-l-pup-maroon' ?> transition-colors flex gap-4 relative group">
                                    <div class="w-10 h-10 rounded-full <?= $bgColor ?> <?= $iconColor ?> flex items-center justify-center flex-shrink-0"><i data-lucide="<?= $icon ?>" class="h-5 w-5"></i></div>
                                    <div class="flex-1">
                                        <div class="flex justify-between items-start mb-1">
                                            <h3 class="font-bold text-gray-900 text-sm"><?= $title ?></h3>
                                            <span class="text-xs font-semibold text-gray-500"><?= time_elapsed_string($notif['created_at']) ?></span>
                                        </div>
                                        <p class="text-sm text-gray-700"><?= $message ?></p>
                                    </div>
                                    <?php if(!$notif['is_read']): ?>
                                        <div class="absolute right-5 top-1/2 transform -translate-y-1/2 w-2.5 h-2.5 bg-pup-maroon rounded-full"></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-8 text-center text-gray-500 text-sm">You have no new notifications.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chatbox Component Included Here -->
        <?php include 'student_chatbox.php'; ?>

    </main>

    <!-- Logout Modal -->
    <div id="logoutModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div id="logoutModalOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity opacity-0 duration-300" aria-hidden="true" onclick="closeLogoutModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div id="logoutModalPanel" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-sm w-full opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95 duration-300">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10"><i data-lucide="log-out" class="h-6 w-6 text-red-600"></i></div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left"><h3 class="text-lg leading-6 font-bold text-gray-900">Sign Out</h3><div class="mt-2"><p class="text-sm text-gray-500">Are you sure you want to sign out?</p></div></div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 flex flex-col sm:flex-row-reverse gap-2">
                    <a href="../auth/logout.php" class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 sm:w-auto sm:text-sm transition-colors text-center">Sign Out</a>
                    <button type="button" onclick="closeLogoutModal()" class="w-full inline-flex justify-center rounded-xl border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:w-auto sm:text-sm transition-colors">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
        function openLogoutModal() { document.getElementById('logoutModalOverlay').classList.replace('opacity-0', 'opacity-100'); document.getElementById('logoutModalPanel').classList.replace('opacity-0', 'opacity-100'); document.getElementById('logoutModalPanel').classList.replace('translate-y-4', 'translate-y-0'); document.getElementById('logoutModalPanel').classList.replace('sm:scale-95', 'sm:scale-100'); document.getElementById('logoutModal').classList.remove('hidden'); }
        function closeLogoutModal() { document.getElementById('logoutModalOverlay').classList.replace('opacity-100', 'opacity-0'); document.getElementById('logoutModalPanel').classList.replace('opacity-100', 'opacity-0'); document.getElementById('logoutModalPanel').classList.replace('translate-y-0', 'translate-y-4'); document.getElementById('logoutModalPanel').classList.replace('sm:scale-100', 'sm:scale-95'); setTimeout(() => document.getElementById('logoutModal').classList.add('hidden'), 300); }
    </script>
</body>
</html>