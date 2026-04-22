<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'faculty', 'super_admin'])) {
    header("Location: ../auth/facultylogin.php");
    exit();
}


$user_id = $_SESSION['user_id'];
$success_msg = '';

// Handle Approval / Rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['clearance_id']) && isset($_POST['action'])) {
    $c_id = intval($_POST['clearance_id']);
    $new_status = $_POST['action'] == 'approve' ? 'Approved' : 'Rejected';
    
    $updateStmt = $pdo->prepare("UPDATE medical_clearances SET status = ?, reviewed_by = ?, approved_date = NOW() WHERE id = ?");
    $updateStmt->execute([$new_status, $user_id, $c_id]);
    
    // Fetch user and clearance details for notifications
    $adminName = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $adminName->execute([$user_id]);
    $admin_full_name = $adminName->fetchColumn();

    $stuStmt = $pdo->prepare("SELECT u.full_name, mc.student_id, mc.purpose FROM medical_clearances mc JOIN users u ON mc.student_id = u.id WHERE mc.id = ?");
    $stuStmt->execute([$c_id]);
    $clearance = $stuStmt->fetch();
    
    if ($clearance) {
        // Notify the student
        $notif_msg = "[Clearance] Faculty " . $admin_full_name . " has " . strtolower($new_status) . " your Medical Clearance request for " . $clearance['purpose'] . ".";
        $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $notifStmt->execute([$clearance['student_id'], $notif_msg]);

        // Notify Admins
        $admin_notif_msg = "[Clearance] Faculty " . $admin_full_name . " " . strtolower($new_status) . " a clearance request for " . $clearance['full_name'] . ".";
        $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (NULL, ?)")->execute([$admin_notif_msg]);
    }
    
    $success_msg = "Clearance request has been " . $new_status . ".";
}

// Get admin info for header
$stmt = $pdo->prepare("SELECT full_name, profile_pic FROM users WHERE id = ?");
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

// Fetch Clearances by Status
try {
    $pendingStmt = $pdo->query("SELECT mc.*, u.full_name as patient_name, u.course, u.section FROM medical_clearances mc JOIN users u ON mc.student_id = u.id WHERE mc.status = 'Pending' ORDER BY mc.created_at ASC");
    $pending = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

    $approvedStmt = $pdo->query("SELECT mc.*, u.full_name as patient_name, u.course, u.section FROM medical_clearances mc JOIN users u ON mc.student_id = u.id WHERE mc.status = 'Approved' ORDER BY mc.approved_date DESC");
    $approved = $approvedStmt->fetchAll(PDO::FETCH_ASSOC);

    $rejectedStmt = $pdo->query("SELECT mc.*, u.full_name as patient_name, u.course, u.section FROM medical_clearances mc JOIN users u ON mc.student_id = u.id WHERE mc.status = 'Rejected' ORDER BY mc.approved_date DESC");
    $rejected = $rejectedStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error fetching clearances: " . $e->getMessage());
}

// Helper Function to Render Each Clearance Card
function renderClearanceCard($clr, $type) {
    $statusClass = $clr['status'] == 'Pending' ? 'bg-yellow-100 text-yellow-800' : ($clr['status'] == 'Approved' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800');
    ?>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 hover:shadow-md transition-shadow">
        <div class="flex justify-between items-start mb-4">
            <div class="bg-blue-100 text-blue-700 p-2.5 rounded-xl"><i data-lucide="file-check-2" class="h-5 w-5"></i></div>
            <span class="text-xs font-bold px-2 py-1 rounded <?= $statusClass ?>">
                <?= htmlspecialchars($clr['status']) ?>
            </span>
        </div>
        <h3 class="font-bold text-gray-900 text-lg"><?= htmlspecialchars($clr['patient_name']) ?></h3>
        <p class="text-xs text-gray-500 mb-4"><?= htmlspecialchars($clr['course'] . ' | Sec: ' . $clr['section']) ?></p>
        
        <div class="space-y-2 text-sm text-gray-600 mb-4">
            <p class="flex items-center gap-2"><i data-lucide="bookmark" class="h-4 w-4"></i> <?= htmlspecialchars($clr['purpose']) ?></p>
            <p class="flex items-center gap-2"><i data-lucide="calendar" class="h-4 w-4"></i> <?= date("M d, Y h:i A", strtotime($clr['created_at'])) ?></p>
            <?php if (!empty($clr['remarks'])): ?>
                <p class="text-xs text-gray-500 italic mt-2">"<?= htmlspecialchars($clr['remarks']) ?>"</p>
            <?php endif; ?>
        </div>
        
        <?php if($type == 'pending'): ?>
            <div class="flex gap-2 mt-4 pt-4 border-t border-gray-100">
                <form method="POST" action="admin_clearance.php" class="flex-1">
                    <input type="hidden" name="clearance_id" value="<?= $clr['id'] ?>">
                    <button type="submit" name="action" value="approve" class="w-full bg-green-50 text-green-700 hover:bg-green-100 py-2 rounded-xl text-xs font-bold transition-colors">Approve</button>
                </form>
                <form method="POST" action="admin_clearance.php" class="flex-1">
                    <input type="hidden" name="clearance_id" value="<?= $clr['id'] ?>">
                    <button type="submit" name="action" value="reject" class="w-full bg-red-50 text-red-700 hover:bg-red-100 py-2 rounded-xl text-xs font-bold transition-colors">Reject</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Clearances - MediLog</title>
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
            <a href="admin_clearance.php" class="flex items-center gap-3 px-4 py-3 bg-pup-maroon text-white rounded-xl font-medium transition-colors shadow-sm"><i data-lucide="file-check-2" class="h-5 w-5"></i> Clearances</a>
            <a href="admin_inquiries.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="message-square" class="h-5 w-5"></i> Inquiries</a>
            <a href="admin_profile.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="user-cog" class="h-5 w-5"></i> Profile</a>
            <a href="super_admin_users.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="shield-alert" class="h-5 w-5"></i> Faculty Management</a>
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
            <a href="admin_clearance.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-pup-maroon transition-colors"><i data-lucide="file-check-2" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Clearances</span></a>
            <a href="admin_inquiries.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="message-square" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Inquiries</span></a>
            <a href="admin_profile.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="user-cog" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Profile</span></a>
            <a href="super_admin_users.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="shield-alert" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Admins</span></a>
        </div>
    </nav>

    <main class="flex-1 flex flex-col h-full overflow-hidden">
        <header class="bg-white border-b border-gray-200 px-4 sm:px-8 py-4 flex items-center justify-between shadow-sm z-10">
            <div class="flex items-center gap-3">
                <div class="md:hidden bg-pup-maroon text-white p-1.5 rounded-lg mr-2"><i data-lucide="shield-plus" class="h-5 w-5 text-pup-gold"></i></div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900">Medical Clearances</h1>
            </div>
            <div class="flex items-center gap-3 sm:gap-4">
                <a href="admin_notifications.php" class="text-gray-500 hover:text-pup-maroon p-2 bg-gray-50 hover:bg-red-50 rounded-full border border-gray-200 relative"><i data-lucide="bell" class="h-5 w-5"></i></a>
                <button onclick="openLogoutModal()" class="md:hidden text-gray-500 hover:text-red-600 transition-colors p-2 bg-gray-50 rounded-full border border-gray-200"><i data-lucide="log-out" class="h-5 w-5"></i></button>
                <div class="hidden md:flex items-center gap-3 pl-4 border-l border-gray-200">
                    <span class="text-sm font-semibold text-gray-700 bg-gray-100 px-3 py-1 rounded-full mr-2"><i data-lucide="shield-check" class="h-4 w-4 inline mr-1 text-green-600"></i> Admin Session</span>
                    <div class="text-right"><p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($user['full_name']) ?></p></div>
                    <img src="<?= $profile_pic ?>" alt="Profile" class="h-10 w-10 rounded-full border-2 border-gray-200 object-cover">
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 sm:p-8 pb-24 md:pb-8">
            <div class="max-w-7xl mx-auto">
                
                <?php if($success_msg): ?>
                    <div class="bg-green-50 text-green-700 p-4 rounded-xl text-sm font-medium border border-green-200 flex items-center gap-2 mb-6">
                        <i data-lucide="check-circle-2" class="h-5 w-5"></i> <?= htmlspecialchars($success_msg) ?>
                    </div>
                <?php endif; ?>

                <!-- TABS NAVIGATION -->
                <div class="border-b border-gray-200 mb-6">
                    <nav class="-mb-px flex gap-6" aria-label="Tabs">
                        <button onclick="switchTab('pending')" id="tab-pending" class="tab-btn border-pup-maroon text-pup-maroon whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2 transition-colors">
                            Pending
                            <span class="bg-yellow-100 text-yellow-800 py-0.5 px-2 rounded-full text-xs font-bold"><?= count($pending) ?></span>
                        </button>
                        <button onclick="switchTab('approved')" id="tab-approved" class="tab-btn border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2 transition-colors">
                            Approved
                            <span class="bg-gray-100 text-gray-600 py-0.5 px-2 rounded-full text-xs font-bold"><?= count($approved) ?></span>
                        </button>
                        <button onclick="switchTab('rejected')" id="tab-rejected" class="tab-btn border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2 transition-colors">
                            Rejected / Cancelled
                            <span class="bg-gray-100 text-gray-600 py-0.5 px-2 rounded-full text-xs font-bold"><?= count($rejected) ?></span>
                        </button>
                    </nav>
                </div>

                <!-- TABS CONTENT -->
                
                <!-- Pending Tab -->
                <div id="content-pending" class="tab-content grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if(count($pending) > 0): ?>
                        <?php foreach($pending as $clr): ?>
                            <?php renderClearanceCard($clr, 'pending'); ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-span-full p-8 text-center text-gray-500 bg-white rounded-2xl border border-gray-100">No pending medical clearances.</div>
                    <?php endif; ?>
                </div>

                <!-- Approved Tab -->
                <div id="content-approved" class="tab-content hidden grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if(count($approved) > 0): ?>
                        <?php foreach($approved as $clr): ?>
                            <?php renderClearanceCard($clr, 'approved'); ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-span-full p-8 text-center text-gray-500 bg-white rounded-2xl border border-gray-100">No approved medical clearances.</div>
                    <?php endif; ?>
                </div>

                <!-- Rejected Tab -->
                <div id="content-rejected" class="tab-content hidden grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if(count($rejected) > 0): ?>
                        <?php foreach($rejected as $clr): ?>
                            <?php renderClearanceCard($clr, 'rejected'); ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-span-full p-8 text-center text-gray-500 bg-white rounded-2xl border border-gray-100">No rejected medical clearances.</div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>

    <div id="logoutModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div id="logoutModalOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity opacity-0 duration-300" aria-hidden="true" onclick="closeLogoutModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div id="logoutModalPanel" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-sm w-full opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95 duration-300">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10"><i data-lucide="log-out" class="h-6 w-6 text-red-600"></i></div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left"><h3 class="text-lg leading-6 font-bold text-gray-900" id="modal-title">Sign Out</h3><div class="mt-2"><p class="text-sm text-gray-500">Are you sure you want to sign out?</p></div></div>
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
        function openLogoutModal() { const m = document.getElementById('logoutModal'); m.classList.remove('hidden'); setTimeout(() => { document.getElementById('logoutModalOverlay').classList.replace('opacity-0', 'opacity-100'); document.getElementById('logoutModalPanel').classList.replace('opacity-0', 'opacity-100'); document.getElementById('logoutModalPanel').classList.replace('translate-y-4', 'translate-y-0'); document.getElementById('logoutModalPanel').classList.replace('sm:scale-95', 'sm:scale-100'); }, 10); }
        function closeLogoutModal() { document.getElementById('logoutModalOverlay').classList.replace('opacity-100', 'opacity-0'); document.getElementById('logoutModalPanel').classList.replace('opacity-100', 'opacity-0'); document.getElementById('logoutModalPanel').classList.replace('translate-y-0', 'translate-y-4'); document.getElementById('logoutModalPanel').classList.replace('sm:scale-100', 'sm:scale-95'); setTimeout(() => document.getElementById('logoutModal').classList.add('hidden'), 300); }
        
        // Tab Logic
        function switchTab(tabId) {
            // Save tab to local storage
            localStorage.setItem('activeClrTab', tabId);

            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            
            // Reset all tab buttons
            document.querySelectorAll('.tab-btn').forEach(el => {
                el.classList.remove('border-pup-maroon', 'text-pup-maroon');
                el.classList.add('border-transparent', 'text-gray-500');
            });

            // Show selected content
        document.getElementById('content-' + tabId).classList.remove('hidden');
        
        // Highlight selected button
        const activeBtn = document.getElementById('tab-' + tabId);
        activeBtn.classList.remove('border-transparent', 'text-gray-500');
        activeBtn.classList.add('border-pup-maroon', 'text-pup-maroon');
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Restore last active tab on load, defaults to 'pending'
        const savedTab = localStorage.getItem('activeClrTab') || 'pending';
        switchTab(savedTab);
    });
    </script>
</body>
</html>