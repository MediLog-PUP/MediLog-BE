<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/studentlogin.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Send message to the clinic
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    if (!empty($message)) {
        // We set receiver_id to 0 to represent the "Shared Clinic Inbox"
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, 0, ?)");
        $stmt->execute([$user_id, $message]);
    }
    header("Location: messages.php");
    exit();
}

// Fetch conversation history. We pull any message where the student is sender OR receiver
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
</head>
<body class="bg-gray-50 flex h-screen overflow-hidden">
    
    <?php include '../global_loader.php'; ?>

    <!-- Sidebar -->
    <aside class="hidden md:flex flex-col w-64 bg-gray-900 text-white h-full shadow-xl z-20">
        <div class="p-6 border-b border-gray-800"><span class="font-bold text-xl">MediLog Student</span></div>
        <nav class="flex-1 px-4 py-6 space-y-2">
            <a href="student_dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 rounded-xl"><i data-lucide="layout-dashboard"></i> Dashboard</a>
            <a href="messages.php" class="flex items-center gap-3 px-4 py-3 bg-pup-maroon text-white rounded-xl shadow-sm"><i data-lucide="message-square"></i> Messages</a>
        </nav>
        <div class="p-4"><a href="../logout.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-red-400 w-full"><i data-lucide="log-out"></i> Sign Out</a></div>
    </aside>

    <main class="flex-1 flex flex-col h-full bg-white max-w-4xl mx-auto border-x border-gray-200 shadow-sm">
        <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center gap-3 z-10">
            <div class="bg-pup-maroon p-2 rounded-full"><i data-lucide="headphones" class="h-6 w-6 text-white"></i></div>
            <div>
                <h1 class="text-xl font-bold text-gray-900">MediLog Support</h1>
                <p class="text-xs text-green-600 font-medium">● Clinic Admins are Online</p>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-6 space-y-4 bg-gray-50" id="chat-container">
            <?php if(empty($chat_history)): ?>
                <div class="text-center text-gray-400 mt-10">
                    <i data-lucide="message-circle" class="h-12 w-12 mx-auto mb-2 opacity-50"></i>
                    <p>Send a message to the clinic. An admin will reply shortly.</p>
                </div>
            <?php else: ?>
                <?php foreach($chat_history as $msg): 
                    // Check if the sender is an admin or super admin
                    $isAdmin = in_array($msg['sender_role'], ['admin', 'faculty', 'super_admin']);
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
                                <!-- Display ONLY "MediLog Support" regardless of which admin replied -->
                                <p class="text-[11px] font-bold text-gray-500 mb-1 ml-1">MediLog Support</p>
                            <?php endif; ?>
                            
                            <div class="p-3.5 rounded-2xl <?= $isMe ? 'bg-pup-maroon text-white rounded-br-none shadow-sm' : 'bg-white border border-gray-200 text-gray-800 rounded-bl-none shadow-sm' ?>">
                                <p class="text-sm"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="p-4 border-t border-gray-200 bg-white">
            <form action="messages.php" method="POST" class="flex gap-2">
                <input type="text" name="message" required placeholder="Type your message to the clinic here..." class="flex-1 px-4 py-3 bg-gray-50 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:bg-white sm:text-sm transition-colors">
                <button type="submit" class="bg-pup-maroon text-white px-6 py-3 rounded-xl font-bold hover:bg-red-900 transition-colors"><i data-lucide="send" class="h-5 w-5"></i></button>
            </form>
        </div>
    </main>

    <script>
        lucide.createIcons(); 
        // Auto-scroll to bottom of chat
        const chatContainer = document.getElementById('chat-container');
        if (chatContainer) chatContainer.scrollTop = chatContainer.scrollHeight;
    </script>
</body>
</html>