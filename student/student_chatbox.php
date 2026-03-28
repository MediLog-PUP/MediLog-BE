<?php
// Expects $pdo and $user_id to be available from the parent file

// Fetch the last faculty/admin who replied to this student
$lastAdminStmt = $pdo->prepare("
    SELECT u.id, u.full_name, u.profile_pic 
    FROM messages m 
    JOIN users u ON m.sender_id = u.id 
    WHERE m.receiver_id = ? AND u.role IN ('admin', 'faculty') 
    ORDER BY m.created_at DESC LIMIT 1
");
$lastAdminStmt->execute([$user_id]);
$active_admin = $lastAdminStmt->fetch(PDO::FETCH_ASSOC);

// If no one has replied yet, fallback to any available admin
if (!$active_admin) {
    $adminStmt = $pdo->query("SELECT id, full_name, profile_pic FROM users WHERE role IN ('admin', 'faculty') LIMIT 1");
    $active_admin = $adminStmt->fetch(PDO::FETCH_ASSOC);
}

$active_chat_id = $active_admin ? $active_admin['id'] : null;
$chat_open = false;

// Handle sending a message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['chat_message']) && $active_chat_id) {
    $msg = trim($_POST['chat_message']);
    if (!empty($msg)) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $active_chat_id, $msg]);
        $chat_open = true; 
    }
}

// Fetch chat history
$chat_messages = [];
if ($active_chat_id) {
    $msgStmt = $pdo->prepare("
        SELECT m.*, u.full_name as sender_name 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE (m.sender_id = ? AND m.receiver_id = ?) 
           OR (m.sender_id = ? AND m.receiver_id = ?) 
        ORDER BY m.created_at ASC
    ");
    $msgStmt->execute([$user_id, $active_chat_id, $active_chat_id, $user_id]);
    $chat_messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!-- Pop-Up Chatbox Trigger -->
<button onclick="toggleChat()" class="fixed bottom-24 right-6 md:bottom-10 md:right-10 bg-pup-maroon hover:bg-pup-maroonDark text-white p-4 rounded-full shadow-2xl transition-transform hover:scale-110 z-40 focus:outline-none focus:ring-4 focus:ring-red-200">
    <i data-lucide="message-square" class="h-6 w-6"></i>
</button>

<!-- Chat Window -->
<div id="chatWindow" class="<?= $chat_open ? '' : 'hidden' ?> fixed bottom-40 right-6 md:bottom-28 md:right-10 w-80 md:w-96 bg-white rounded-2xl shadow-2xl border border-gray-200 z-50 flex flex-col overflow-hidden transition-all duration-300 origin-bottom-right" style="height: 500px; max-height: 70vh;">
    <div class="bg-pup-maroon p-4 text-white flex justify-between items-center shadow-sm z-10">
        <div class="flex items-center gap-3">
            <?php 
                $admin_pic = 'https://ui-avatars.com/api/?name='.urlencode($active_admin['full_name'] ?? 'Admin').'&background=fff&color=880000';
                if (!empty($active_admin['profile_pic']) && $active_admin['profile_pic'] !== 'default.png') {
                    if (strpos($active_admin['profile_pic'], 'data:image') === 0) {
                        $admin_pic = $active_admin['profile_pic'];
                    } else {
                        $admin_pic = '../uploads/profiles/' . htmlspecialchars($active_admin['profile_pic']);
                    }
                }
            ?>
            <img src="<?= $admin_pic ?>" class="w-10 h-10 rounded-full border-2 border-white/50 object-cover shadow-sm">
            <div>
                <!-- SHOW ACTUAL NAME -->
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
                <div class="flex justify-end">
                    <div class="bg-pup-maroon text-white rounded-2xl rounded-tr-sm px-4 py-2.5 max-w-[80%] text-sm shadow-sm">
                        <?= nl2br(htmlspecialchars($msg['message'])) ?>
                        <!-- Shows Date and Time -->
                        <div class="text-[9px] text-white/70 mt-1 text-right"><?= date("M d, Y h:i A", strtotime($msg['created_at'])) ?></div>
                    </div>
                </div>
            <?php else: ?>
                <div class="flex justify-start flex-col items-start mb-1">
                    <!-- Display Faculty Name Above Their Message Bubble -->
                    <span class="text-[10px] text-gray-500 font-bold ml-3 mb-1"><?= htmlspecialchars($msg['sender_name']) ?></span>
                    <div class="bg-white border border-gray-200 text-gray-800 rounded-2xl rounded-tl-sm px-4 py-2.5 max-w-[80%] text-sm shadow-sm">
                        <?= nl2br(htmlspecialchars($msg['message'])) ?>
                        <!-- Shows Date and Time -->
                        <div class="text-[9px] text-gray-400 mt-1 text-left"><?= date("M d, Y h:i A", strtotime($msg['created_at'])) ?></div>
                    </div>
                </div>
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

<script>
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
    });
</script>