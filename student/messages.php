<?php
session_start();
require '../db_connect.php'; 

// Redirect to login if not authenticated as a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/studentlogin.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Fetch Clinic Administrators (to start a chat)
$adminStmt = $pdo->query("SELECT id, full_name, profile_pic FROM users WHERE role IN ('admin', 'faculty')");
$admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);

$active_chat_id = isset($_GET['admin_id']) ? intval($_GET['admin_id']) : (count($admins) > 0 ? $admins[0]['id'] : null);

// Handle sending a new message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message']) && $active_chat_id) {
    $msg = trim($_POST['message']);
    if (!empty($msg)) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $active_chat_id, $msg]);
        header("Location: messages.php?admin_id=" . $active_chat_id);
        exit();
    }
}

// Fetch active chat messages
$messages = [];
$active_admin = null;
if ($active_chat_id) {
    $admStmt = $pdo->prepare("SELECT full_name, profile_pic FROM users WHERE id = ?");
    $admStmt->execute([$active_chat_id]);
    $active_admin = $admStmt->fetch();

    $msgStmt = $pdo->prepare("SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC");
    $msgStmt->execute([$user_id, $active_chat_id, $active_chat_id, $user_id]);
    $messages = $msgStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - MediLog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] }, colors: { pup: { maroon: '#880000', maroonDark: '#660000', gold: '#F1B500', goldLight: '#FDE68A' } } } } }
    </script>
    <style>
        body { opacity: 0; animation: fadeIn 0.4s ease-out forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="font-sans antialiased text-gray-800 bg-gray-50 flex h-screen overflow-hidden">

    <?php include '../global_loader.php'; ?>

    <!-- Sidebar -->
    <aside class="hidden md:flex flex-col w-64 bg-gray-900 text-white h-full shadow-xl z-20 flex-shrink-0">
        <div class="p-6 flex items-center gap-3 border-b border-gray-800">
            <div class="bg-pup-maroon text-white p-2 rounded-lg">
                <i data-lucide="clipboard-heart" class="h-6 w-6 text-pup-gold"></i>
            </div>
            <span class="font-bold text-xl tracking-tight text-white">MediLog</span>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <a href="student_dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="layout-dashboard" class="h-5 w-5"></i> Dashboard</a>
            <a href="book_appointment.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="calendar-plus" class="h-5 w-5"></i> Book Appointment</a>
            <a href="health_records.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="folder-clock" class="h-5 w-5"></i> Health Records</a>
            <a href="medical_clearance.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="file-check-2" class="h-5 w-5"></i> Medical Clearance</a>
            <a href="messages.php" class="flex items-center gap-3 px-4 py-3 bg-pup-maroon text-white rounded-xl font-medium transition-colors shadow-sm"><i data-lucide="message-square" class="h-5 w-5"></i> Messages</a>
            <a href="student_profile.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="user" class="h-5 w-5"></i> Profile</a>
        </nav>
        <div class="p-4 border-t border-gray-800">
            <button onclick="openLogoutModal()" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-red-900/50 hover:text-red-400 rounded-xl font-medium transition-colors w-full text-left">
                <i data-lucide="log-out" class="h-5 w-5"></i> Sign Out
            </button>
        </div>
    </aside>

    <!-- Mobile Bottom Navigation -->
    <nav class="md:hidden fixed bottom-0 left-0 w-full bg-white border-t border-gray-200 z-50 overflow-x-auto no-scrollbar shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
        <div class="flex items-center w-max px-2 min-w-full justify-between">
            <a href="student_dashboard.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="layout-dashboard" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Home</span></a>
            <a href="book_appointment.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="calendar-plus" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Book</span></a>
            <a href="health_records.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="folder-clock" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Records</span></a>
            <a href="medical_clearance.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="file-check-2" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Clearance</span></a>
            <a href="messages.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-pup-maroon transition-colors relative"><i data-lucide="message-square" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Messages</span></a>
            <a href="student_profile.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="user" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Profile</span></a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col md:flex-row h-full overflow-hidden bg-white pb-[72px] md:pb-0">
        
        <!-- Conversation List -->
        <div class="w-full md:w-1/3 lg:w-1/4 border-r border-gray-200 flex flex-col h-full bg-gray-50">
            <div class="p-4 border-b border-gray-200 bg-white flex items-center justify-between z-10 shadow-sm">
                <h2 class="text-xl font-bold text-gray-900">Clinic Staff</h2>
                <div class="flex items-center gap-2">
                    <a href="student_notifications.php" class="text-gray-500 hover:text-pup-maroon transition-colors p-2 bg-gray-50 hover:bg-red-50 rounded-full border border-gray-200 hover:border-red-100 relative"><i data-lucide="bell" class="h-5 w-5"></i></a>
                    <button onclick="openLogoutModal()" class="md:hidden text-gray-500 hover:text-red-600 transition-colors p-2 bg-gray-50 rounded-full border border-gray-200"><i data-lucide="log-out" class="h-5 w-5"></i></button>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto divide-y divide-gray-100">
                <?php if(count($admins) > 0): ?>
                    <?php foreach($admins as $admin): 
                        $pic = (!empty($admin['profile_pic']) && $admin['profile_pic'] !== 'default.png') ? '../uploads/profiles/' . $admin['profile_pic'] : 'https://ui-avatars.com/api/?name='.urlencode($admin['full_name']);
                        $isActive = ($active_chat_id == $admin['id']) ? 'bg-red-50 border-l-4 border-pup-maroon' : 'hover:bg-gray-100 border-l-4 border-transparent bg-white';
                    ?>
                        <a href="messages.php?admin_id=<?= $admin['id'] ?>" class="p-4 flex items-center gap-3 cursor-pointer transition-colors <?= $isActive ?>">
                            <img src="<?= $pic ?>" class="w-10 h-10 rounded-full object-cover border border-gray-200">
                            <div class="flex-1 overflow-hidden">
                                <h3 class="font-bold text-gray-900 text-sm truncate"><?= htmlspecialchars($admin['full_name']) ?></h3>
                                <p class="text-xs text-gray-500">Clinic Staff</p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-4 text-center text-gray-500 text-sm">No clinic staff available.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="hidden md:flex flex-1 flex-col h-full relative bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] bg-gray-50/50">
            <?php if($active_admin): 
                $active_pic = (!empty($active_admin['profile_pic']) && $active_admin['profile_pic'] !== 'default.png') ? '../uploads/profiles/' . $active_admin['profile_pic'] : 'https://ui-avatars.com/api/?name='.urlencode($active_admin['full_name']);
            ?>
            <div class="p-4 border-b border-gray-200 bg-white flex items-center shadow-sm z-10">
                <img src="<?= $active_pic ?>" class="w-10 h-10 rounded-full mr-3 object-cover border border-gray-200">
                <div>
                    <h2 class="font-bold text-gray-900"><?= htmlspecialchars($active_admin['full_name']) ?></h2>
                    <span class="text-xs text-green-600 flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500"></span> Online</span>
                </div>
            </div>
            
            <div class="flex-1 overflow-y-auto p-6 space-y-4" id="chatBox">
                <?php if(empty($messages)): ?>
                    <div class="text-center text-xs text-gray-400 my-4 font-medium">Send a message to start the conversation</div>
                <?php endif; ?>

                <?php foreach($messages as $msg): ?>
                    <?php if($msg['sender_id'] == $user_id): ?>
                        <div class="flex justify-end">
                            <div class="bg-gray-100 rounded-2xl rounded-tr-sm px-4 py-3 max-w-[75%] text-sm text-gray-800 shadow-sm border border-gray-200">
                                <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                <div class="text-[10px] text-gray-400 mt-1 text-right"><?= date("h:i A", strtotime($msg['created_at'])) ?></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="flex justify-start">
                            <div class="bg-pup-maroon text-white rounded-2xl rounded-tl-sm px-4 py-3 max-w-[75%] text-sm shadow-md">
                                <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                <div class="text-[10px] text-white/70 mt-1 text-right"><?= date("h:i A", strtotime($msg['created_at'])) ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <div class="p-4 border-t border-gray-200 bg-white">
                <form action="messages.php?admin_id=<?= $active_chat_id ?>" method="POST" class="flex gap-2">
                    <input type="text" name="message" placeholder="Type your inquiry here..." class="flex-1 px-4 py-3 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon text-sm bg-gray-50" required autocomplete="off">
                    <button type="submit" class="bg-pup-maroon hover:bg-pup-maroonDark text-white px-5 py-2 rounded-xl transition-colors shadow-sm flex justify-center items-center gap-2 font-semibold text-sm">
                        Send <i data-lucide="send" class="h-4 w-4"></i>
                    </button>
                </form>
            </div>
            <script>
                const chatBox = document.getElementById("chatBox");
                if(chatBox) chatBox.scrollTop = chatBox.scrollHeight;
            </script>
            <?php endif; ?>
        </div>
    </main>

    <!-- Logout Confirmation Modal -->
    <div id="logoutModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div id="logoutModalOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity opacity-0 duration-300" aria-hidden="true" onclick="closeLogoutModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div id="logoutModalPanel" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-sm w-full opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95 duration-300">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10"><i data-lucide="log-out" class="h-6 w-6 text-red-600"></i></div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-bold text-gray-900">Sign Out</h3>
                            <div class="mt-2"><p class="text-sm text-gray-500">Are you sure you want to sign out?</p></div>
                        </div>
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
        function openLogoutModal() {
            document.getElementById('logoutModal').classList.remove('hidden');
            setTimeout(() => {
                document.getElementById('logoutModalOverlay').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('logoutModalPanel').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('logoutModalPanel').classList.replace('translate-y-4', 'translate-y-0');
                document.getElementById('logoutModalPanel').classList.replace('sm:scale-95', 'sm:scale-100');
            }, 10);
        }
        function closeLogoutModal() {
            document.getElementById('logoutModalOverlay').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('logoutModalPanel').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('logoutModalPanel').classList.replace('translate-y-0', 'translate-y-4');
            document.getElementById('logoutModalPanel').classList.replace('sm:scale-100', 'sm:scale-95');
            setTimeout(() => document.getElementById('logoutModal').classList.add('hidden'), 300);
        }
    </script>
</body>
</html>