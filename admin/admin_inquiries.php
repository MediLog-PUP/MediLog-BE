<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'faculty'])) {
    header("Location: ../auth/facultylogin.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$is_chat_selected = isset($_GET['student_id']);
$active_chat_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;

// Handle AJAX Fetching of Messages (Polling)
if (isset($_GET['ajax_fetch_chat']) && $active_chat_id) {
    $msgStmt = $pdo->prepare("SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC");
    $msgStmt->execute([$admin_id, $active_chat_id, $active_chat_id, $admin_id]);
    $messages = $msgStmt->fetchAll();
    
    ob_start();
    if(empty($messages)) {
        echo '<div class="text-center text-xs text-gray-400 my-4 font-medium">No messages yet. Reply to start the conversation.</div>';
    }
    foreach($messages as $msg) {
        if($msg['sender_id'] == $admin_id) {
            echo '<div class="flex justify-end">
                    <div class="bg-gray-800 text-white rounded-2xl rounded-tr-sm px-4 py-3 max-w-[75%] text-sm shadow-md">'
                        . nl2br(htmlspecialchars($msg['message'])) .
                        '<div class="text-[10px] text-white/60 mt-1 text-right">' . date("M d, Y h:i A", strtotime($msg['created_at'])) . '</div>
                    </div>
                  </div>';
        } else {
            echo '<div class="flex justify-start">
                    <div class="bg-white border border-gray-200 text-gray-800 rounded-2xl rounded-tl-sm px-4 py-3 max-w-[75%] text-sm shadow-sm">'
                        . nl2br(htmlspecialchars($msg['message'])) .
                        '<div class="text-[10px] text-gray-400 mt-1 text-left">' . date("M d, Y h:i A", strtotime($msg['created_at'])) . '</div>
                    </div>
                  </div>';
        }
    }
    echo ob_get_clean();
    exit;
}

// Handle AJAX Sending of Message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_send_msg']) && $active_chat_id) {
    $msg = trim($_POST['message']);
    if (!empty($msg)) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$admin_id, $active_chat_id, $msg]);

        // Get admin info for notification
        $stmtAdmin = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $stmtAdmin->execute([$admin_id]);
        $admin_name = $stmtAdmin->fetchColumn();

        // Create Admin Notification
        $stuNameStmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $stuNameStmt->execute([$active_chat_id]);
        $stu_name = $stuNameStmt->fetchColumn();

        $admin_notif_msg = "[Message] Faculty " . $admin_name . " responded to an inquiry from " . $stu_name . ".";
        $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (NULL, ?)")->execute([$admin_notif_msg]);

        // Notify the student
        $stu_notif_msg = "[Message] Faculty " . $admin_name . " replied to your inquiry.";
        $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")->execute([$active_chat_id, $stu_notif_msg]);
    }
    echo json_encode(["status" => "success"]);
    exit();
}

// Regular Page Load Initialization
$stmt = $pdo->prepare("SELECT full_name, profile_pic FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$user = $stmt->fetch();

$profile_pic = 'https://ui-avatars.com/api/?name=' . urlencode($user['full_name']) . '&background=880000&color=fff';
if (!empty($user['profile_pic']) && $user['profile_pic'] !== 'default.png') {
    if (strpos($user['profile_pic'], 'data:image') === 0) {
        $profile_pic = $user['profile_pic'];
    } else {
        $profile_pic = '../uploads/profiles/' . htmlspecialchars($user['profile_pic']);
    }
}

// Fetch all students to display in the chat sidebar, WITH their profile pictures
$studentStmt = $pdo->query("SELECT id, full_name, profile_pic FROM users WHERE role = 'student' ORDER BY full_name ASC");
$students = $studentStmt->fetchAll(PDO::FETCH_ASSOC);

// Auto-select first on desktop
if (!$is_chat_selected && count($students) > 0) {
    $active_chat_id = $students[0]['id']; 
}

$messages = [];
$active_student = null;

if ($active_chat_id) {
    $stuStmt = $pdo->prepare("SELECT full_name, profile_pic FROM users WHERE id = ?");
    $stuStmt->execute([$active_chat_id]);
    $active_student = $stuStmt->fetch();

    $msgStmt = $pdo->prepare("SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC");
    $msgStmt->execute([$admin_id, $active_chat_id, $active_chat_id, $admin_id]);
    $messages = $msgStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Inquiries - MediLog</title>
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

    <aside class="hidden md:flex flex-col w-64 bg-gray-900 text-white h-full shadow-xl z-20 flex-shrink-0">
        <div class="p-6 flex items-center gap-3 border-b border-gray-800">
            <div class="bg-pup-gold text-gray-900 p-2 rounded-lg"><i data-lucide="shield-plus" class="h-6 w-6"></i></div>
            <span class="font-bold text-xl tracking-tight text-white">MediLog Admin</span>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <a href="admin_dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="layout-dashboard" class="h-5 w-5"></i> Overview</a>
            <a href="medicine_inventory.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="pill" class="h-5 w-5"></i> Inventory</a>
            <a href="patient_records.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="users" class="h-5 w-5"></i> Patient Records</a>
            <a href="admin_treatment_records.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="clipboard-list" class="h-5 w-5"></i> Treatment Records</a>
            <a href="admin_appointments.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="calendar" class="h-5 w-5"></i> Appointments</a>
            <a href="admin_clearance.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="file-check-2" class="h-5 w-5"></i> Clearances</a>
            <a href="admin_inquiries.php" class="flex items-center gap-3 px-4 py-3 bg-pup-maroon text-white rounded-xl font-medium transition-colors shadow-sm"><i data-lucide="message-square" class="h-5 w-5"></i> Inquiries</a>
            <a href="admin_profile.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="user-cog" class="h-5 w-5"></i> Profile</a>
            <a href="super_admin_users.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="shield-alert" class="h-5 w-5"></i> Admin Management</a>
        </nav>
        <div class="p-4 border-t border-gray-800">
            <button onclick="openLogoutModal()" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-red-900/50 hover:text-red-400 rounded-xl font-medium transition-colors w-full text-left"><i data-lucide="log-out" class="h-5 w-5"></i> Sign Out</button>
        </div>
    </aside>

    <nav class="md:hidden fixed bottom-0 left-0 w-full bg-white border-t border-gray-200 z-50 overflow-x-auto no-scrollbar shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
        <div class="flex items-center w-max px-2 min-w-full justify-between">
            <a href="admin_dashboard.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="layout-dashboard" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Home</span></a>
            <a href="medicine_inventory.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="pill" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Inventory</span></a>
            <a href="patient_records.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="users" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Patients</span></a>
            <a href="admin_treatment_records.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="clipboard-list" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Treatments</span></a>
            <a href="admin_appointments.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="calendar" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Schedule</span></a>
            <a href="admin_clearance.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="file-check-2" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Clearances</span></a>
            <a href="admin_inquiries.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-pup-maroon transition-colors"><i data-lucide="message-square" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Inquiries</span></a>
            <a href="admin_profile.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="user-cog" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Profile</span></a>
            <a href="super_admin_users.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="shield-alert" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Admins</span></a>
        </div>
    </nav>

    <main class="flex-1 flex flex-col h-full overflow-hidden relative bg-gray-50">
        
        <!-- Global Header (Hidden on Mobile when in an active chat) -->
        <header class="<?= $is_chat_selected ? 'hidden md:flex' : 'flex' ?> bg-white border-b border-gray-200 px-4 md:px-8 py-4 items-center justify-between z-10 shadow-sm flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="md:hidden bg-pup-maroon text-white p-1.5 rounded-lg mr-2"><i data-lucide="shield-plus" class="h-5 w-5 text-pup-gold"></i></div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900">Inquiries</h1>
            </div>
            <div class="flex items-center gap-3 sm:gap-4">
                <a href="admin_notifications.php" class="text-gray-500 hover:text-pup-maroon p-2 bg-gray-50 hover:bg-red-50 rounded-full border border-gray-200 relative"><i data-lucide="bell" class="h-5 w-5"></i></a>
                <button onclick="openLogoutModal()" class="md:hidden text-gray-500 hover:text-red-600 transition-colors p-2 bg-gray-50 rounded-full border border-gray-200"><i data-lucide="log-out" class="h-5 w-5"></i></button>
                <div class="hidden md:flex items-center gap-3 pl-4 border-l border-gray-200">
                    <span class="text-sm font-semibold text-gray-700 bg-gray-100 px-3 py-1 rounded-full mr-2"><i data-lucide="shield-check" class="h-4 w-4 inline mr-1 text-green-600"></i> Admin Session</span>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($user['full_name']) ?></p>
                    </div>
                    <img src="<?= $profile_pic ?>" alt="Profile" class="h-10 w-10 rounded-full border-2 border-gray-200 object-cover">
                </div>
            </div>
        </header>

        <!-- Chat Split Layout -->
        <div class="flex-1 flex flex-col md:flex-row overflow-hidden bg-white pb-[72px] md:pb-0">
            <!-- Conversation List (Sidebar) -->
            <div class="<?= $is_chat_selected ? 'hidden md:flex' : 'flex' ?> w-full md:w-1/3 lg:w-1/4 border-r border-gray-200 flex-col h-full bg-gray-50">
                <div class="p-4 border-b border-gray-200 bg-white flex items-center justify-between z-10 shadow-sm flex-shrink-0">
                    <h2 class="text-lg font-bold text-gray-900">Student Chats</h2>
                </div>
                <div class="flex-1 overflow-y-auto divide-y divide-gray-100">
                    <?php if(count($students) > 0): ?>
                        <?php foreach($students as $stu): 
                            $s_pic = 'https://ui-avatars.com/api/?name='.urlencode($stu['full_name']).'&background=fff&color=880000';
                            if (!empty($stu['profile_pic']) && $stu['profile_pic'] !== 'default.png') {
                                if (strpos($stu['profile_pic'], 'data:image') === 0) {
                                    $s_pic = $stu['profile_pic'];
                                } else {
                                    $s_pic = '../uploads/profiles/' . htmlspecialchars($stu['profile_pic']);
                                }
                            }
                            $isActive = ($active_chat_id == $stu['id']) ? 'bg-red-50 border-l-4 border-pup-maroon' : 'hover:bg-gray-100 border-l-4 border-transparent bg-white';
                        ?>
                            <a href="admin_inquiries.php?student_id=<?= $stu['id'] ?>" class="p-4 flex items-center gap-3 cursor-pointer transition-colors <?= $isActive ?>">
                                <img src="<?= $s_pic ?>" class="w-10 h-10 rounded-full object-cover border border-gray-200 shadow-sm">
                                <div class="flex-1 overflow-hidden">
                                    <h3 class="font-bold text-gray-900 text-sm truncate"><?= htmlspecialchars($stu['full_name']) ?></h3>
                                    <p class="text-xs text-gray-500">Student Patient</p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-4 text-center text-gray-500 text-sm">No students found.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Chat Area -->
            <div class="<?= $is_chat_selected ? 'flex' : 'hidden md:flex' ?> flex-1 flex-col h-full relative bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] bg-gray-50/50">
                <?php if($active_student): 
                    $active_pic = 'https://ui-avatars.com/api/?name='.urlencode($active_student['full_name']).'&background=fff&color=880000';
                    if (!empty($active_student['profile_pic']) && $active_student['profile_pic'] !== 'default.png') {
                        if (strpos($active_student['profile_pic'], 'data:image') === 0) {
                            $active_pic = $active_student['profile_pic'];
                        } else {
                            $active_pic = '../uploads/profiles/' . htmlspecialchars($active_student['profile_pic']);
                        }
                    }
                ?>
                <div class="p-4 border-b border-gray-200 bg-white flex items-center shadow-sm z-10 justify-between flex-shrink-0">
                    <div class="flex items-center">
                        <a href="admin_inquiries.php" class="md:hidden mr-3 p-1.5 rounded-lg bg-gray-50 text-gray-500 hover:text-gray-900 transition-colors border border-gray-200">
                            <i data-lucide="arrow-left" class="h-5 w-5"></i>
                        </a>
                        <img src="<?= $active_pic ?>" class="w-10 h-10 rounded-full mr-3 object-cover border border-gray-200 shadow-sm">
                        <div>
                            <h2 class="font-bold text-gray-900"><?= htmlspecialchars($active_student['full_name']) ?></h2>
                            <span class="text-xs text-gray-500 font-medium">Student</span>
                        </div>
                    </div>
                </div>
                
                <div class="flex-1 overflow-y-auto p-6 space-y-4" id="chatBox">
                    <?php if(empty($messages)): ?>
                        <div class="text-center text-xs text-gray-400 my-4 font-medium">No messages yet. Reply to start the conversation.</div>
                    <?php endif; ?>

                    <?php foreach($messages as $msg): ?>
                        <?php if($msg['sender_id'] == $admin_id): ?>
                            <div class="flex justify-end">
                                <div class="bg-gray-800 text-white rounded-2xl rounded-tr-sm px-4 py-3 max-w-[75%] text-sm shadow-md">
                                    <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                    <div class="text-[10px] text-white/60 mt-1 text-right"><?= date("M d, Y h:i A", strtotime($msg['created_at'])) ?></div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="flex justify-start">
                                <div class="bg-white border border-gray-200 text-gray-800 rounded-2xl rounded-tl-sm px-4 py-3 max-w-[75%] text-sm shadow-sm">
                                    <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                    <div class="text-[10px] text-gray-400 mt-1 text-left"><?= date("M d, Y h:i A", strtotime($msg['created_at'])) ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                
                <div class="p-4 border-t border-gray-200 bg-white flex-shrink-0">
                    <form onsubmit="sendAdminMessage(event, this)" class="flex gap-2">
                        <input type="text" name="message" placeholder="Type a response..." class="flex-1 px-4 py-3 border border-gray-300 rounded-xl focus:ring-gray-900 focus:border-gray-900 text-sm bg-gray-50" required autocomplete="off">
                        <button type="submit" class="bg-gray-900 hover:bg-gray-800 text-white px-5 py-2 rounded-xl transition-colors shadow-sm flex justify-center items-center gap-2 font-semibold text-sm">
                            Reply <i data-lucide="send" class="h-4 w-4"></i>
                        </button>
                    </form>
                </div>
                
                <script>
                    const activeChatId = <?= $active_chat_id ? $active_chat_id : 'null' ?>;
                    const chatBox = document.getElementById("chatBox");
                    if(chatBox) chatBox.scrollTop = chatBox.scrollHeight;

                    // Fetch Messages Real-Time (Polling)
                    if (activeChatId) {
                        setInterval(() => {
                            fetch(`admin_inquiries.php?student_id=${activeChatId}&ajax_fetch_chat=1`)
                            .then(res => res.text())
                            .then(html => {
                                if(!chatBox) return;
                                const isScrolledToBottom = chatBox.scrollHeight - chatBox.clientHeight <= chatBox.scrollTop + 50;
                                chatBox.innerHTML = html;
                                if (isScrolledToBottom) chatBox.scrollTop = chatBox.scrollHeight;
                            })
                            .catch(err => console.error("Polling Error:", err));
                        }, 2000);
                    }

                    // Send Message via AJAX
                    function sendAdminMessage(e, form) {
                        e.preventDefault();
                        const input = form.querySelector('input[name="message"]');
                        const msg = input.value;
                        if(!msg.trim()) return;

                        const formData = new FormData();
                        formData.append('ajax_send_msg', '1');
                        formData.append('message', msg);

                        input.value = ''; // Instantly clear input for better UX

                        fetch(`admin_inquiries.php?student_id=${activeChatId}`, {
                            method: 'POST',
                            body: formData
                        })
                        .then(() => {
                            // Immediately trigger a fetch after sending
                            return fetch(`admin_inquiries.php?student_id=${activeChatId}&ajax_fetch_chat=1`);
                        })
                        .then(res => res.text())
                        .then(html => {
                            if(chatBox) {
                                chatBox.innerHTML = html;
                                chatBox.scrollTop = chatBox.scrollHeight;
                            }
                        })
                        .catch(err => console.error("Send Error:", err));
                    }
                </script>
                <?php else: ?>
                    <div class="flex-1 flex flex-col items-center justify-center text-gray-400">
                        <i data-lucide="message-square-off" class="h-16 w-16 mb-4 text-gray-300"></i>
                        <p>Select a student to view inquiries</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <div id="logoutModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div id="logoutModalOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity opacity-0 duration-300" onclick="closeLogoutModal()"></div>
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
        function openLogoutModal() { const m = document.getElementById('logoutModal'); m.classList.remove('hidden'); setTimeout(() => { document.getElementById('logoutModalOverlay').classList.replace('opacity-0', 'opacity-100'); document.getElementById('logoutModalPanel').classList.replace('opacity-0', 'opacity-100'); document.getElementById('logoutModalPanel').classList.replace('translate-y-4', 'translate-y-0'); document.getElementById('logoutModalPanel').classList.replace('sm:scale-95', 'sm:scale-100'); }, 10); }
        function closeLogoutModal() { document.getElementById('logoutModalOverlay').classList.replace('opacity-100', 'opacity-0'); document.getElementById('logoutModalPanel').classList.replace('opacity-100', 'opacity-0'); document.getElementById('logoutModalPanel').classList.replace('translate-y-0', 'translate-y-4'); document.getElementById('logoutModalPanel').classList.replace('sm:scale-100', 'sm:scale-95'); setTimeout(() => document.getElementById('logoutModal').classList.add('hidden'), 300); }
    </script>
</body>
</html>