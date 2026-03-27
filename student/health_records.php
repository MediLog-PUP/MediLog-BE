<?php
session_start();
require '../db_connect.php'; 

// Redirect to login if not authenticated as a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/studentlogin.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user info for the header
$stmt = $pdo->prepare("SELECT full_name, course, profile_pic FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Fetch actual health records for this student
$recordsStmt = $pdo->prepare("SELECT hr.*, u.full_name as physician_name FROM health_records hr LEFT JOIN users u ON hr.physician_id = u.id WHERE hr.patient_id = ? ORDER BY visit_date DESC");
$recordsStmt->execute([$user_id]);
$records = $recordsStmt->fetchAll();

// --- CHATBOX LOGIC ---
$adminStmt = $pdo->query("SELECT id, full_name, profile_pic FROM users WHERE role IN ('admin', 'faculty')");
$admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);
$active_chat_id = count($admins) > 0 ? $admins[0]['id'] : null;
$active_admin = $admins[0] ?? null;

$chat_open = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['chat_message']) && $active_chat_id) {
    $msg = trim($_POST['chat_message']);
    if (!empty($msg)) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $active_chat_id, $msg]);
        $chat_open = true; 
    }
}

$chat_messages = [];
if ($active_chat_id) {
    $msgStmt = $pdo->prepare("SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC");
    $msgStmt->execute([$user_id, $active_chat_id, $active_chat_id, $user_id]);
    $chat_messages = $msgStmt->fetchAll();
}
// ---------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Records - MediLog</title>
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

    <!-- Sidebar -->
    <aside class="hidden md:flex flex-col w-64 bg-gray-900 text-white h-full shadow-xl z-20 flex-shrink-0">
        <div class="p-6 flex items-center gap-3 border-b border-gray-800">
            <div class="bg-pup-maroon text-white p-2 rounded-lg"><i data-lucide="clipboard-heart" class="h-6 w-6 text-pup-gold"></i></div>
            <span class="font-bold text-xl tracking-tight text-white">MediLog</span>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <a href="student_dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="layout-dashboard" class="h-5 w-5"></i> Dashboard</a>
            <a href="book_appointment.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="calendar-plus" class="h-5 w-5"></i> Book Appointment</a>
            <a href="health_records.php" class="flex items-center gap-3 px-4 py-3 bg-pup-maroon text-white rounded-xl font-medium transition-colors shadow-sm"><i data-lucide="folder-clock" class="h-5 w-5"></i> Health Records</a>
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
            <a href="health_records.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-pup-maroon transition-colors"><i data-lucide="folder-clock" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Records</span></a>
            <a href="medical_clearance.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="file-check-2" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Clearance</span></a>
            <a href="#" onclick="toggleChat(); return false;" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors relative"><i data-lucide="message-square" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Messages</span></a>
            <a href="student_profile.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="user" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Profile</span></a>
        </div>
    </nav>

    <main class="flex-1 flex flex-col h-full overflow-hidden relative">
        <header class="bg-white border-b border-gray-200 px-4 md:px-8 py-4 flex items-center justify-between z-10 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="md:hidden bg-pup-maroon text-white p-1.5 rounded-lg mr-2"><i data-lucide="clipboard-heart" class="h-5 w-5 text-pup-gold"></i></div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900">Health Records</h1>
            </div>
            <div class="flex items-center gap-3 sm:gap-4">
                <a href="student_notifications.php" class="text-gray-500 hover:text-pup-maroon p-2 bg-gray-50 hover:bg-red-50 rounded-full border border-gray-200 relative"><i data-lucide="bell" class="h-5 w-5"></i></a>
                <button onclick="openLogoutModal()" class="md:hidden text-gray-500 hover:text-red-600 transition-colors p-2 bg-gray-50 rounded-full border border-gray-200"><i data-lucide="log-out" class="h-5 w-5"></i></button>
                <div class="hidden md:flex items-center gap-3 pl-4 border-l border-gray-200">
                    <div class="text-right"><p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($user['full_name']) ?></p></div>
                    <?php $profile_pic = !empty($user['profile_pic']) && $user['profile_pic'] !== 'default.png' ? '../uploads/profiles/' . htmlspecialchars($user['profile_pic']) : 'https://ui-avatars.com/api/?name=' . urlencode($user['full_name']) . '&background=880000&color=fff'; ?>
                    <img src="<?= $profile_pic ?>" alt="Profile" class="h-10 w-10 rounded-full border-2 border-gray-200 object-cover hidden md:block">
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 sm:p-8 pb-24 md:pb-8">
            <div class="max-w-6xl mx-auto space-y-6">
                
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                        <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2"><i data-lucide="folder-open" class="h-5 w-5 text-pup-maroon"></i> Medical History</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-white text-gray-500 text-xs uppercase tracking-wider border-b border-gray-200">
                                    <th class="p-4 font-semibold">Date</th>
                                    <th class="p-4 font-semibold">Service / Reason</th>
                                    <th class="p-4 font-semibold">Physician</th>
                                    <th class="p-4 font-semibold">Diagnosis / Notes</th>
                                    <th class="p-4 font-semibold">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-sm">
                                <?php if(count($records) > 0): ?>
                                    <?php foreach($records as $hr): ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="p-4 text-gray-900 font-medium whitespace-nowrap"><?= date("M d, Y", strtotime($hr['visit_date'])) ?></td>
                                            <td class="p-4 font-bold text-gray-900"><?= htmlspecialchars($hr['service_reason']) ?></td>
                                            <td class="p-4 text-gray-600"><?= htmlspecialchars($hr['physician_name'] ?? 'Clinic Staff') ?></td>
                                            <td class="p-4 text-gray-500 max-w-xs truncate" title="<?= htmlspecialchars($hr['diagnosis']) ?>"><?= htmlspecialchars($hr['diagnosis']) ?></td>
                                            <td class="p-4">
                                                <span class="px-2.5 py-1 bg-green-50 text-green-700 rounded-full text-xs font-semibold border border-green-100"><?= htmlspecialchars($hr['status']) ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="p-8 text-center text-gray-500">No health records found in the system.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- Pop-Up Chatbox Trigger -->
        <button onclick="toggleChat()" class="fixed bottom-24 right-6 md:bottom-10 md:right-10 bg-pup-maroon hover:bg-pup-maroonDark text-white p-4 rounded-full shadow-2xl transition-transform hover:scale-110 z-40 focus:outline-none focus:ring-4 focus:ring-red-200">
            <i data-lucide="message-square" class="h-6 w-6"></i>
        </button>

        <!-- Chat Window -->
        <div id="chatWindow" class="<?= $chat_open ? '' : 'hidden' ?> fixed bottom-40 right-6 md:bottom-28 md:right-10 w-80 md:w-96 bg-white rounded-2xl shadow-2xl border border-gray-200 z-50 flex flex-col overflow-hidden transition-all duration-300 origin-bottom-right" style="height: 500px; max-height: 70vh;">
            <div class="bg-pup-maroon p-4 text-white flex justify-between items-center shadow-sm z-10">
                <div class="flex items-center gap-3">
                    <?php 
                        $admin_pic = (!empty($active_admin['profile_pic']) && $active_admin['profile_pic'] !== 'default.png') ? '../uploads/profiles/' . $active_admin['profile_pic'] : 'https://ui-avatars.com/api/?name='.urlencode($active_admin['full_name'] ?? 'Admin').'&background=fff&color=880000';
                    ?>
                    <img src="<?= $admin_pic ?>" class="w-10 h-10 rounded-full border-2 border-white/50 object-cover shadow-sm">
                    <div>
                        <h4 class="font-bold text-sm tracking-wide"><?= htmlspecialchars($active_admin['full_name'] ?? 'Clinic Support') ?></h4>
                        <p class="text-[10px] text-green-300 flex items-center gap-1 font-medium tracking-wider"><span class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse"></span> ONLINE</p>
                    </div>
                </div>
                <button onclick="toggleChat()" class="text-white/80 hover:text-white transition-colors focus:outline-none bg-white/10 p-1.5 rounded-lg hover:bg-white/20"><i data-lucide="x" class="h-5 w-5"></i></button>
            </div>
            <div class="flex-1 p-4 overflow-y-auto bg-gray-50 flex flex-col gap-3" id="chatMessagesContainer">
                <?php if(empty($chat_messages)): ?>
                    <div class="text-center text-xs text-gray-400 my-auto font-medium">Send a message to start the conversation</div>
                <?php endif; ?>
                <?php foreach($chat_messages as $msg): ?>
                    <?php if($msg['sender_id'] == $user_id): ?>
                        <div class="flex justify-end"><div class="bg-pup-maroon text-white rounded-2xl rounded-tr-sm px-4 py-2.5 max-w-[80%] text-sm shadow-sm"><?= nl2br(htmlspecialchars($msg['message'])) ?><div class="text-[9px] text-white/70 mt-1 text-right"><?= date("h:i A", strtotime($msg['created_at'])) ?></div></div></div>
                    <?php else: ?>
                        <div class="flex justify-start"><div class="bg-white border border-gray-200 text-gray-800 rounded-2xl rounded-tl-sm px-4 py-2.5 max-w-[80%] text-sm shadow-sm"><?= nl2br(htmlspecialchars($msg['message'])) ?><div class="text-[9px] text-gray-400 mt-1 text-left"><?= date("h:i A", strtotime($msg['created_at'])) ?></div></div></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <div class="p-3 bg-white border-t border-gray-100 shadow-sm z-10">
                <form action="" method="POST" class="flex gap-2">
                    <input type="text" name="chat_message" placeholder="Type an inquiry..." class="flex-1 px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon text-sm bg-gray-50 transition-colors" required autocomplete="off">
                    <button type="submit" class="bg-pup-maroon text-white px-4 rounded-xl hover:bg-pup-maroonDark transition-colors shadow-sm flex items-center justify-center"><i data-lucide="send" class="h-4 w-4"></i></button>
                </form>
            </div>
        </div>
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
                    <a href="../logout.php" class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 sm:w-auto sm:text-sm transition-colors text-center">Sign Out</a>
                    <button type="button" onclick="closeLogoutModal()" class="w-full inline-flex justify-center rounded-xl border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:w-auto sm:text-sm transition-colors">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
        function openLogoutModal() { document.getElementById('logoutModalOverlay').classList.replace('opacity-0', 'opacity-100'); document.getElementById('logoutModalPanel').classList.replace('opacity-0', 'opacity-100'); document.getElementById('logoutModalPanel').classList.replace('translate-y-4', 'translate-y-0'); document.getElementById('logoutModalPanel').classList.replace('sm:scale-95', 'sm:scale-100'); document.getElementById('logoutModal').classList.remove('hidden'); }
        function closeLogoutModal() { document.getElementById('logoutModalOverlay').classList.replace('opacity-100', 'opacity-0'); document.getElementById('logoutModalPanel').classList.replace('opacity-100', 'opacity-0'); document.getElementById('logoutModalPanel').classList.replace('translate-y-0', 'translate-y-4'); document.getElementById('logoutModalPanel').classList.replace('sm:scale-100', 'sm:scale-95'); setTimeout(() => document.getElementById('logoutModal').classList.add('hidden'), 300); }
        
        // Chatbox Logic
        function toggleChat() {
            const chatWindow = document.getElementById('chatWindow');
            if (chatWindow.classList.contains('hidden')) {
                chatWindow.classList.remove('hidden');
                setTimeout(() => {
                    chatWindow.classList.remove('scale-95', 'opacity-0');
                    chatWindow.classList.add('scale-100', 'opacity-100');
                    scrollToBottom();
                }, 10);
            } else {
                chatWindow.classList.remove('scale-100', 'opacity-100');
                chatWindow.classList.add('scale-95', 'opacity-0');
                setTimeout(() => {
                    chatWindow.classList.add('hidden');
                }, 300);
            }
        }

        function scrollToBottom() {
            const container = document.getElementById('chatMessagesContainer');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        }

        document.addEventListener('DOMContentLoaded', () => { 
            <?php if($chat_open): ?>
                scrollToBottom();
            <?php endif; ?>
            document.querySelectorAll('a[href]:not([href^="#"]):not([target="_blank"]):not([onclick])').forEach(link => { 
                link.addEventListener('click', e => { 
                    const href = link.getAttribute('href'); 
                    if (!href || href === "javascript:void(0);") return; 
                    e.preventDefault(); document.body.classList.add('page-exit'); 
                    setTimeout(() => window.location.href = href, 250); 
                }); 
            }); 
        });
    </script>
</body>
</html>