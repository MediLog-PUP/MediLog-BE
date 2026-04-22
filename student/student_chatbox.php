<?php
// Secure direct access routing for AJAX Requests
$is_ajax_call = false;
if (!isset($pdo)) {
    session_start();
    require '../db_connect.php';
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') exit();
    $user_id = $_SESSION['user_id'];
    $is_ajax_call = true;
}

// --- AJAX ENDPOINTS ---
// Handle AJAX Sending
if (isset($_POST['ajax_send_msg'])) {
    ob_clean(); // Ensure a clean JSON response
    header('Content-Type: application/json');

    try {
        $msg = trim($_POST['chat_message']);
        if (!empty($msg)) {
            // FIX 1: Find the admin who last messaged this student to keep the thread alive
            $stmtAdmin = $pdo->prepare("SELECT sender_id FROM messages WHERE receiver_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmtAdmin->execute([$user_id]);
            $admin_id = $stmtAdmin->fetchColumn();

            // FIX 2: If no previous chat, fallback to ANY active staff member safely (prevents inserting '0')
            if (!$admin_id) {
                $admin_id = $pdo->query("SELECT id FROM users WHERE role != 'student' ORDER BY id ASC LIMIT 1")->fetchColumn();
            }

            // Only attempt to insert if a valid admin ID was found
            if ($admin_id) {
                $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $admin_id, $msg]);
                
                // Notify the Admin safely
                try {
                    $stuNameStmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
                    $stuNameStmt->execute([$user_id]);
                    $stu_name = $stuNameStmt->fetchColumn();

                    $admin_notif_msg = "[Message] Student " . $stu_name . " sent a new message inquiry via Chatbox.";
                    $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (NULL, ?)")->execute([$admin_notif_msg]);
                } catch (Exception $e) {
                    // Silently ignore notification errors so it doesn't break the chat execution
                }
            }
        }
        echo json_encode(['status' => 'ok']);
    } catch (Exception $e) {
        // Return errors cleanly so JS doesn't crash
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// Handle AJAX Fetching (Polling)
if (isset($_GET['ajax_fetch_chat'])) {
    ob_clean(); // Clean buffer
    
    // STRICT CACHE BUSTING: Prevent browsers from showing old, cached messages with admin names
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    
    // GUARANTEE PRIVACY: We completely removed the JOIN to the users table.
    // The admin's name is never fetched from the database for the student view.
    $msgStmt = $pdo->prepare("
        SELECT message, sender_id, created_at FROM messages 
        WHERE sender_id = ? OR receiver_id = ? 
        ORDER BY created_at ASC
    ");
    $msgStmt->execute([$user_id, $user_id]);
    $chat_messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);

    if(empty($chat_messages)) {
        echo '<div class="text-center text-xs text-gray-400 my-auto font-medium">Send a message to start the conversation with the clinic.</div>';
    }
    foreach($chat_messages as $msg) {
        if($msg['sender_id'] == $user_id) {
            echo '<div class="flex justify-end">
                    <div class="bg-pup-maroon text-white rounded-2xl rounded-tr-sm px-4 py-2.5 max-w-[80%] text-sm shadow-sm">'
                        . nl2br(htmlspecialchars($msg['message'])) .
                        '<div class="text-[9px] text-white/70 mt-1 text-right">' . date("M d, Y h:i A", strtotime($msg['created_at'])) . '</div>
                    </div>
                  </div>';
        } else {
            // ALWAYS MASK ADMIN IDENTITY FOR STUDENTS
            echo '<div class="flex justify-start flex-col items-start mb-1">
                    <span class="text-[10px] text-gray-500 font-bold ml-3 mb-1">MediLog Support</span>
                    <div class="bg-white border border-gray-200 text-gray-800 rounded-2xl rounded-tl-sm px-4 py-2.5 max-w-[80%] text-sm shadow-sm">'
                        . nl2br(htmlspecialchars($msg['message'])) .
                        '<div class="text-[9px] text-gray-400 mt-1 text-left">' . date("M d, Y h:i A", strtotime($msg['created_at'])) . '</div>
                    </div>
                  </div>';
        }
    }
    exit();
}

// Stop execution if direct ajax call bypassed the conditions
if ($is_ajax_call) exit();

// If not ajax, continue to render the Chatbox HTML snippet for include
$chat_open = false; 
?>

<!-- Pop-Up Chatbox Trigger -->
<button onclick="toggleChat()" class="fixed bottom-24 right-6 md:bottom-10 md:right-10 bg-pup-maroon hover:bg-pup-maroonDark text-white p-4 rounded-full shadow-2xl transition-transform hover:scale-110 z-40 focus:outline-none focus:ring-4 focus:ring-red-200">
    <i data-lucide="message-square" class="h-6 w-6"></i>
</button>

<!-- Chat Window -->
<div id="chatWindow" class="<?= $chat_open ? '' : 'hidden' ?> fixed bottom-40 right-6 md:bottom-28 md:right-10 w-80 md:w-96 bg-white rounded-2xl shadow-2xl border border-gray-200 z-50 flex flex-col overflow-hidden transition-all duration-300 origin-bottom-right" style="height: 500px; max-height: 70vh;">
    <div class="bg-pup-maroon p-4 text-white flex justify-between items-center shadow-sm z-10">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-white flex justify-center items-center shadow-sm">
                <i data-lucide="shield-plus" class="h-6 w-6 text-pup-maroon"></i>
            </div>
            <div>
                <!-- MASKED HEADER NAME -->
                <h4 class="font-bold text-sm tracking-wide">MediLog Support</h4>
                <p class="text-[10px] text-green-300 flex items-center gap-1 font-medium tracking-wider"><span class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse"></span> ONLINE</p>
            </div>
        </div>
        <button onclick="toggleChat()" class="text-white/80 hover:text-white transition-colors focus:outline-none bg-white/10 p-1.5 rounded-lg hover:bg-white/20"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    
    <div class="flex-1 p-4 overflow-y-auto bg-gray-50 flex flex-col gap-3" id="chatMessagesContainer">
        <!-- Will be filled by real-time AJAX automatically on load -->
    </div>
    
    <div class="p-3 bg-white border-t border-gray-100 shadow-sm z-10">
        <form onsubmit="sendStudentMessage(event, this)" class="flex gap-2">
            <input type="text" name="chat_message" placeholder="Type an inquiry..." class="flex-1 px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon text-sm bg-gray-50 transition-colors" required autocomplete="off">
            <button type="submit" class="bg-pup-maroon text-white px-4 rounded-xl hover:bg-pup-maroonDark transition-colors shadow-sm flex items-center justify-center"><i data-lucide="send" class="h-4 w-4"></i></button>
        </form>
    </div>
</div>

<script>
    // Chatbox Visibility Logic
    function toggleChat() {
        const chatWindow = document.getElementById('chatWindow');
        if (chatWindow.classList.contains('hidden')) {
            chatWindow.classList.remove('hidden');
            setTimeout(() => {
                chatWindow.classList.remove('scale-95', 'opacity-0');
                chatWindow.classList.add('scale-100', 'opacity-100');
                pollMessages(); // Fetch immediately on open
            }, 10);
        } else {
            chatWindow.classList.remove('scale-100', 'opacity-100');
            chatWindow.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                chatWindow.classList.add('hidden');
            }, 300);
        }
    }

    // Real-Time Polling Logic
    const chatContainer = document.getElementById('chatMessagesContainer');
    
    function pollMessages() {
        if (document.getElementById('chatWindow').classList.contains('hidden')) return; // Don't poll if hidden
        
        // Cache-busting URL parameter added to force the browser to fetch fresh, masked data
        const timestamp = new Date().getTime();
        fetch('student_chatbox.php?ajax_fetch_chat=1&t=' + timestamp)
        .then(res => res.text())
        .then(html => {
            if(!chatContainer) return;
            const isScrolledToBottom = chatContainer.scrollHeight - chatContainer.clientHeight <= chatContainer.scrollTop + 50;
            
            chatContainer.innerHTML = html;
            
            if (isScrolledToBottom) {
                chatContainer.scrollTop = chatContainer.scrollHeight;
            }
        })
        .catch(err => console.error("Chat polling error:", err));
    }

    setInterval(pollMessages, 2000);

    // Send Message AJAX Logic
    function sendStudentMessage(e, form) {
        e.preventDefault();
        const input = form.querySelector('input[name="chat_message"]');
        const msg = input.value;
        if(!msg.trim()) return;

        const formData = new FormData();
        formData.append('ajax_send_msg', '1');
        formData.append('chat_message', msg);

        input.value = ''; // Instant clear

        fetch('student_chatbox.php', {
            method: 'POST',
            body: formData
        })
        .then(() => pollMessages()) // Instantly fetch to show new message
        .catch(err => console.error("Chat send error:", err));
    }

    document.addEventListener('DOMContentLoaded', () => {
        pollMessages();
    });
</script>