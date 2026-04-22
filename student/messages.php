<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/studentlogin.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle AJAX Fetching (Polling)
if (isset($_GET['ajax_fetch_chat'])) {
    ob_clean(); // Prevent PHP warnings from corrupting the HTML response
    
    // STRICT CACHE BUSTING: Prevent browsers from showing old, cached messages
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");

    $msgStmt = $pdo->prepare("
        SELECT m.*, u.role as sender_role 
        FROM messages m 
        LEFT JOIN users u ON m.sender_id = u.id 
        WHERE m.sender_id = ? OR m.receiver_id = ? 
        ORDER BY m.created_at ASC
    ");
    $msgStmt->execute([$user_id, $user_id]);
    $chat_history = $msgStmt->fetchAll(PDO::FETCH_ASSOC);

    if(empty($chat_history)) {
        echo '<div class="text-center text-gray-400 mt-10">
                <i data-lucide="message-circle" class="h-12 w-12 mx-auto mb-2 opacity-50"></i>
                <p>Send a message to the clinic. An admin will reply shortly.</p>
              </div>';
    } else {
        foreach($chat_history as $msg) {
            $isAdmin = in_array($msg['sender_role'], ['admin', 'faculty', 'super_admin', 'dentist']);
            $isMe = ($msg['sender_id'] == $user_id);
            
            echo '<div class="flex ' . ($isMe ? 'justify-end' : 'justify-start') . ' mb-4">';
            if($isAdmin && !$isMe) {
                echo '<div class="h-8 w-8 bg-pup-maroon rounded-full flex items-center justify-center mr-2 flex-shrink-0">
                        <i data-lucide="shield-plus" class="h-4 w-4 text-white"></i>
                      </div>';
            }
            echo '<div class="max-w-[75%]">';
            if($isAdmin && !$isMe) {
                echo '<p class="text-[11px] font-bold text-gray-500 mb-1 ml-1">MediLog Support</p>';
            }
            $bubbleClass = $isMe ? 'bg-pup-maroon text-white rounded-br-none shadow-sm' : 'bg-white border border-gray-200 text-gray-800 rounded-bl-none shadow-sm';
            echo '<div class="p-3.5 rounded-2xl ' . $bubbleClass . '">
                    <p class="text-sm">' . nl2br(htmlspecialchars($msg['message'])) . '</p>
                    <p class="text-[9px] ' . ($isMe ? 'text-white/70 text-right' : 'text-gray-400 text-left') . ' mt-1">' . date("M d, Y h:i A", strtotime($msg['created_at'])) . '</p>
                  </div>';
            echo '</div></div>';
        }
    }
    exit();
}

// Handle AJAX Sending of Message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_send_msg'])) {
    ob_clean(); // Ensure a clean JSON response
    header('Content-Type: application/json');
    
    try {
        $message = trim($_POST['message']);
        if (!empty($message)) {
            
            // FIX 1: First try to find the admin who last messaged this student to keep the thread alive
            $stmtAdmin = $pdo->prepare("SELECT sender_id FROM messages WHERE receiver_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmtAdmin->execute([$user_id]);
            $admin_id = $stmtAdmin->fetchColumn();

            // FIX 2: If no previous chat, fallback to ANY active staff member safely
            if (!$admin_id) {
                $admin_id = $pdo->query("SELECT id FROM users WHERE role != 'student' ORDER BY id ASC LIMIT 1")->fetchColumn();
            }

            // Only attempt to insert if a valid admin ID was found
            if ($admin_id) {
                $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $admin_id, $message]);

                // Notify the Admin
                try {
                    $stuNameStmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
                    $stuNameStmt->execute([$user_id]);
                    $stu_name = $stuNameStmt->fetchColumn();

                    $admin_notif_msg = "[Message] Student " . $stu_name . " sent a new message inquiry.";
                    $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (NULL, ?)")->execute([$admin_notif_msg]);
                } catch (Exception $e) {
                    // Silently ignore notification errors so it doesn't break the chat execution
                }
            }
        }
        echo json_encode(["status" => "success"]);
    } catch (Exception $e) {
        // Return errors cleanly so JS doesn't crash
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit();
}

// Regular Initial Page Load
$msgStmt = $pdo->prepare("
    SELECT m.*, u.role as sender_role 
    FROM messages m 
    LEFT JOIN users u ON m.sender_id = u.id 
    WHERE m.sender_id = ? OR m.receiver_id = ? 
    ORDER BY m.created_at ASC
");
$msgStmt->execute([$user_id, $user_id]);
$chat_history = $msgStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Messages - MediLog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { pup: { maroon: '#880000', gold: '#F1B500' } } } } }
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
<body class="bg-gray-50 flex h-screen overflow-hidden">
    
    <?php include '../global_loader.php'; ?>

    <!-- Sidebar -->
    <aside class="hidden md:flex flex-col w-64 bg-gray-900 text-white h-full shadow-xl z-20 flex-shrink-0">
        <div class="p-6 border-b border-gray-800 flex items-center gap-3">
            <div class="bg-pup-maroon text-white p-2 rounded-lg"><i data-lucide="clipboard-heart" class="h-6 w-6 text-pup-gold"></i></div>
            <span class="font-bold text-xl tracking-tight">MediLog Student</span>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto no-scrollbar">
            <a href="student_dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl transition-colors"><i data-lucide="layout-dashboard" class="h-5 w-5"></i> Dashboard</a>
            <a href="book_appointment.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl transition-colors"><i data-lucide="calendar-plus" class="h-5 w-5"></i> Book Appointment</a>
            <a href="health_records.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl transition-colors"><i data-lucide="folder-clock" class="h-5 w-5"></i> Health Records</a>
            <a href="medical_clearance.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl transition-colors"><i data-lucide="file-check-2" class="h-5 w-5"></i> Medical Clearance</a>
            <a href="messages.php" class="flex items-center gap-3 px-4 py-3 bg-pup-maroon text-white rounded-xl shadow-sm"><i data-lucide="message-square" class="h-5 w-5"></i> Messages</a>
            <a href="student_profile.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl transition-colors"><i data-lucide="user" class="h-5 w-5"></i> Profile</a>
        </nav>
        <div class="p-4 border-t border-gray-800">
            <a href="../logout.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-red-400 w-full rounded-xl transition-colors"><i data-lucide="log-out" class="h-5 w-5"></i> Sign Out</a>
        </div>
    </aside>

    <!-- Mobile Bottom Navigation -->
    <nav class="md:hidden fixed bottom-0 left-0 w-full bg-white border-t border-gray-200 z-50 overflow-x-auto no-scrollbar shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
        <div class="flex items-center w-max px-2 min-w-full justify-between">
            <a href="student_dashboard.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="layout-dashboard" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Home</span></a>
            <a href="book_appointment.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="calendar-plus" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Book</span></a>
            <a href="health_records.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="folder-clock" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Records</span></a>
            <a href="medical_clearance.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="file-check-2" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Clearance</span></a>
            <a href="messages.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-pup-maroon transition-colors relative"><i data-lucide="message-square" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Messages</span><span class="absolute top-0 w-8 h-1 bg-pup-maroon rounded-b-md"></span></a>
            <a href="student_profile.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="user" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Profile</span></a>
        </div>
    </nav>

    <main class="flex-1 flex flex-col h-full bg-white max-w-4xl mx-auto border-x border-gray-200 shadow-sm relative pb-[72px] md:pb-0">
        <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center gap-3 z-10 flex-shrink-0 shadow-sm">
            <div class="bg-pup-maroon p-2 rounded-full"><i data-lucide="headphones" class="h-6 w-6 text-white"></i></div>
            <div>
                <h1 class="text-xl font-bold text-gray-900">MediLog Support</h1>
                <p class="text-xs text-green-600 font-medium flex items-center gap-1"><span class="h-1.5 w-1.5 bg-green-500 rounded-full animate-pulse"></span> Clinic Admins are Online</p>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4 bg-gray-50" id="chat-container">
            <?php if(empty($chat_history)): ?>
                <div class="text-center text-gray-400 mt-10">
                    <i data-lucide="message-circle" class="h-12 w-12 mx-auto mb-2 opacity-50"></i>
                    <p>Send a message to the clinic. An admin will reply shortly.</p>
                </div>
            <?php else: ?>
                <?php foreach($chat_history as $msg): 
                    $isAdmin = in_array($msg['sender_role'], ['admin', 'faculty', 'super_admin', 'dentist']);
                    $isMe = ($msg['sender_id'] == $user_id);
                ?>
                    <div class="flex <?= $isMe ? 'justify-end' : 'justify-start' ?> mb-4">
                        <?php if($isAdmin && !$isMe): ?>
                            <div class="h-8 w-8 bg-pup-maroon rounded-full flex items-center justify-center mr-2 flex-shrink-0">
                                <i data-lucide="shield-plus" class="h-4 w-4 text-white"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="max-w-[75%]">
                            <?php if($isAdmin && !$isMe): ?>
                                <p class="text-[11px] font-bold text-gray-500 mb-1 ml-1">MediLog Support</p>
                            <?php endif; ?>
                            
                            <div class="p-3.5 rounded-2xl <?= $isMe ? 'bg-pup-maroon text-white rounded-br-none shadow-sm' : 'bg-white border border-gray-200 text-gray-800 rounded-bl-none shadow-sm' ?>">
                                <p class="text-sm"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                                <p class="text-[9px] <?= $isMe ? 'text-white/70 text-right' : 'text-gray-400 text-left' ?> mt-1"><?= date("M d, Y h:i A", strtotime($msg['created_at'])) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="p-4 border-t border-gray-200 bg-white flex-shrink-0 z-10">
            <form onsubmit="sendStudentMessage(event, this)" class="flex gap-2">
                <input type="text" name="message" required autocomplete="off" placeholder="Type your message to the clinic here..." class="flex-1 px-4 py-3 bg-gray-50 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:bg-white sm:text-sm transition-colors shadow-sm">
                <button type="submit" class="bg-pup-maroon text-white px-6 py-3 rounded-xl font-bold hover:bg-red-900 transition-colors shadow-sm flex items-center gap-2"><i data-lucide="send" class="h-5 w-5"></i> <span class="hidden sm:inline">Send</span></button>
            </form>
        </div>
    </main>

    <script>
        lucide.createIcons(); 
        
        const chatContainer = document.getElementById('chat-container');
        if (chatContainer) chatContainer.scrollTop = chatContainer.scrollHeight;

        // AJAX Polling for Real-Time Messages
        setInterval(() => {
            fetch('messages.php?ajax_fetch_chat=1&t=' + new Date().getTime())
            .then(res => res.text())
            .then(html => {
                if(!chatContainer) return;
                const isScrolledToBottom = chatContainer.scrollHeight - chatContainer.clientHeight <= chatContainer.scrollTop + 50;
                
                chatContainer.innerHTML = html;
                
                if(isScrolledToBottom) {
                    chatContainer.scrollTop = chatContainer.scrollHeight;
                }
                lucide.createIcons();
            })
            .catch(err => console.error("Polling error:", err));
        }, 2000);

        // AJAX Send Message (Bulletproof version)
        function sendStudentMessage(e, form) {
            e.preventDefault();
            const input = form.querySelector('input[name="message"]');
            const msg = input.value;
            if(!msg.trim()) return;

            const formData = new FormData();
            formData.append('ajax_send_msg', '1');
            formData.append('message', msg);

            input.value = ''; // Instantly clear input

            fetch('messages.php', {
                method: 'POST',
                body: formData
            })
            .then(() => {
                // Instantly fetch the chat regardless of server warnings
                return fetch('messages.php?ajax_fetch_chat=1&t=' + new Date().getTime());
            })
            .then(res => res.text())
            .then(html => {
                chatContainer.innerHTML = html;
                chatContainer.scrollTop = chatContainer.scrollHeight;
                lucide.createIcons();
            })
            .catch(err => console.error("Send error:", err));
        }
    </script>
</body>
</html>