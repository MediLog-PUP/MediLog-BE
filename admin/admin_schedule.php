<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'faculty', 'super_admin'])) {
    header("Location: ../auth/facultylogin.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Auto-create schedule tables if they don't exist
$pdo->exec("CREATE TABLE IF NOT EXISTS clinic_hours (id INT AUTO_INCREMENT PRIMARY KEY, start_time TIME, end_time TIME)");
$check = $pdo->query("SELECT COUNT(*) FROM clinic_hours")->fetchColumn();
if ($check == 0) {
    $pdo->exec("INSERT INTO clinic_hours (start_time, end_time) VALUES ('08:00:00', '17:00:00')");
}
$pdo->exec("CREATE TABLE IF NOT EXISTS unavailable_dates (id INT AUTO_INCREMENT PRIMARY KEY, closed_date DATE, reason VARCHAR(255))");

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'update_hours') {
        $start = $_POST['start_time'];
        $end = $_POST['end_time'];
        
        if ($start < $end) {
            $stmt = $pdo->prepare("UPDATE clinic_hours SET start_time = ?, end_time = ? WHERE id = 1");
            $stmt->execute([$start, $end]);
            $success_msg = "Clinic operating hours updated successfully!";
        } else {
            $error_msg = "Opening time must be earlier than closing time.";
        }
    } elseif ($action === 'add_date') {
        $date = $_POST['closed_date'];
        $reason = trim($_POST['reason']);
        
        $stmt = $pdo->prepare("SELECT id FROM unavailable_dates WHERE closed_date = ?");
        $stmt->execute([$date]);
        if ($stmt->fetch()) {
            $error_msg = "This date is already marked as closed.";
        } else {
            $insert = $pdo->prepare("INSERT INTO unavailable_dates (closed_date, reason) VALUES (?, ?)");
            $insert->execute([$date, $reason]);
            $success_msg = "Date blocked successfully.";
        }
    } elseif ($action === 'delete_date') {
        $del_id = intval($_POST['date_id']);
        $pdo->prepare("DELETE FROM unavailable_dates WHERE id = ?")->execute([$del_id]);
        $success_msg = "Blocked date removed. It is now open for bookings.";
    }
}

// Fetch Current Data
$hours = $pdo->query("SELECT * FROM clinic_hours LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$closed_dates = $pdo->query("SELECT * FROM unavailable_dates ORDER BY closed_date ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Admin Profile
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Schedule - MediLog</title>
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
            <a href="admin_schedule.php" class="flex items-center gap-3 px-4 py-3 bg-pup-maroon text-white rounded-xl font-medium transition-colors shadow-sm"><i data-lucide="clock" class="h-5 w-5"></i> Clinic Schedule</a>
            <a href="admin_clearance.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="file-check-2" class="h-5 w-5"></i> Clearances</a>
            <a href="admin_inquiries.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="message-square" class="h-5 w-5"></i> Inquiries</a>
            <a href="admin_profile.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="user-cog" class="h-5 w-5"></i> Profile</a>
            <a href="super_admin_users.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="shield-alert" class="h-5 w-5"></i> Admin Management</a>
        </nav>
        <div class="p-4 border-t border-gray-800">
            <a href="../logout.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-red-900/50 hover:text-red-400 rounded-xl font-medium transition-colors w-full"><i data-lucide="log-out" class="h-5 w-5"></i> Sign Out</a>
        </div>
    </aside>

    <!-- MOBILE BOTTOM BAR -->
    <nav class="md:hidden fixed bottom-0 left-0 w-full bg-white border-t border-gray-200 shadow-[0_-4px_10px_rgba(0,0,0,0.05)] z-40 pb-safe">
        <div class="flex items-center overflow-x-auto no-scrollbar snap-x snap-mandatory">
            <a href="admin_dashboard.php" class="snap-center flex-1 min-w-[70px] flex flex-col items-center justify-center p-3 text-gray-400 hover:text-pup-maroon transition-colors relative">
                <i data-lucide="layout-dashboard" class="h-6 w-6 mb-1"></i><span class="text-[10px] font-bold">Home</span>
            </a>
            <a href="admin_appointments.php" class="snap-center flex-1 min-w-[70px] flex flex-col items-center justify-center p-3 text-gray-400 hover:text-pup-maroon transition-colors relative">
                <i data-lucide="calendar" class="h-6 w-6 mb-1"></i><span class="text-[10px] font-bold">Appts</span>
            </a>
            <a href="admin_schedule.php" class="snap-center flex-1 min-w-[70px] flex flex-col items-center justify-center p-3 text-pup-maroon transition-colors relative">
                <i data-lucide="clock" class="h-6 w-6 mb-1"></i><span class="text-[10px] font-bold">Schedule</span><span class="absolute top-0 w-8 h-1 bg-pup-maroon rounded-b-md"></span>
            </a>
            <a href="admin_inquiries.php" class="snap-center flex-1 min-w-[70px] flex flex-col items-center justify-center p-3 text-gray-400 hover:text-pup-maroon transition-colors relative">
                <i data-lucide="message-square" class="h-6 w-6 mb-1"></i><span class="text-[10px] font-bold">Chat</span>
            </a>
        </div>
    </nav>

    <main class="flex-1 flex flex-col h-full overflow-hidden bg-gray-50 relative pb-20 md:pb-0">
        <header class="bg-white border-b border-gray-200 px-4 sm:px-8 py-4 flex items-center justify-between shadow-sm z-10 flex-shrink-0">
            <h1 class="text-xl sm:text-2xl font-bold text-gray-900">Clinic Schedule Settings</h1>
            <div class="flex items-center gap-3 sm:gap-4">
                <p class="text-sm font-semibold text-gray-900 hidden sm:block"><?= htmlspecialchars($user['full_name']) ?></p>
                <img src="<?= $profile_pic ?>" alt="Profile" class="h-9 w-9 sm:h-10 sm:w-10 rounded-full border-2 border-gray-200 object-cover">
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 sm:p-8">
            <div class="max-w-5xl mx-auto space-y-6">

                <?php if($success_msg): ?>
                    <div class="bg-green-50 text-green-700 p-4 rounded-xl text-sm font-medium border border-green-200 flex items-center gap-2"><i data-lucide="check-circle-2" class="h-5 w-5"></i> <?= htmlspecialchars($success_msg) ?></div>
                <?php endif; ?>
                <?php if($error_msg): ?>
                    <div class="bg-red-50 text-red-700 p-4 rounded-xl text-sm font-medium border border-red-200 flex items-center gap-2"><i data-lucide="alert-circle" class="h-5 w-5"></i> <?= htmlspecialchars($error_msg) ?></div>
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <!-- Panel 1: Operating Hours -->
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 sm:p-8">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="bg-blue-100 p-2 rounded-lg text-blue-600"><i data-lucide="clock" class="h-6 w-6"></i></div>
                            <div>
                                <h2 class="text-lg font-bold text-gray-900">Operating Hours</h2>
                                <p class="text-xs text-gray-500">Set daily opening and closing times.</p>
                            </div>
                        </div>
                        
                        <form action="admin_schedule.php" method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="update_hours">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Opening Time</label>
                                    <input type="time" name="start_time" value="<?= htmlspecialchars($hours['start_time']) ?>" required class="block w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Closing Time</label>
                                    <input type="time" name="end_time" value="<?= htmlspecialchars($hours['end_time']) ?>" required class="block w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                                </div>
                            </div>
                            <div class="bg-yellow-50 text-yellow-800 p-3 rounded-lg text-xs font-medium border border-yellow-100 flex items-start gap-2">
                                <i data-lucide="info" class="h-4 w-4 flex-shrink-0"></i>
                                <p>Saturdays and Sundays are automatically closed and disabled for students by default.</p>
                            </div>
                            <button type="submit" class="w-full bg-pup-maroon hover:bg-pup-maroonDark text-white px-4 py-2.5 rounded-xl font-bold transition-colors">Save Hours</button>
                        </form>
                    </div>

                    <!-- Panel 2: Block Dates -->
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 sm:p-8">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="bg-red-100 p-2 rounded-lg text-red-600"><i data-lucide="calendar-off" class="h-6 w-6"></i></div>
                            <div>
                                <h2 class="text-lg font-bold text-gray-900">Block Special Dates</h2>
                                <p class="text-xs text-gray-500">Close the clinic for holidays or events.</p>
                            </div>
                        </div>

                        <form action="admin_schedule.php" method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="add_date">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Select Date</label>
                                <input type="date" name="closed_date" required min="<?= date('Y-m-d') ?>" class="block w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Reason (Optional)</label>
                                <input type="text" name="reason" placeholder="e.g. National Holiday" class="block w-full px-4 py-2 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                            </div>
                            <button type="submit" class="w-full bg-gray-900 hover:bg-black text-white px-4 py-2.5 rounded-xl font-bold transition-colors mt-2">Add to Block List</button>
                        </form>
                    </div>
                </div>

                <!-- Table: Blocked Dates -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="p-5 border-b border-gray-200 bg-gray-50">
                        <h3 class="font-bold text-gray-900">Currently Blocked Dates</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-white text-gray-500 text-xs uppercase tracking-wider border-b border-gray-200">
                                    <th class="p-4 font-semibold">Date Blocked</th>
                                    <th class="p-4 font-semibold">Reason</th>
                                    <th class="p-4 font-semibold text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-sm">
                                <?php if(count($closed_dates) > 0): ?>
                                    <?php foreach($closed_dates as $cd): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="p-4 font-bold text-gray-900"><?= date("l, M d, Y", strtotime($cd['closed_date'])) ?></td>
                                            <td class="p-4 text-gray-600"><?= htmlspecialchars($cd['reason'] ?? 'Not specified') ?></td>
                                            <td class="p-4 text-right">
                                                <form action="admin_schedule.php" method="POST" onsubmit="return confirm('Remove this date from the block list?');">
                                                    <input type="hidden" name="action" value="delete_date">
                                                    <input type="hidden" name="date_id" value="<?= $cd['id'] ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors">Unblock</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="p-8 text-center text-gray-500">No upcoming dates are blocked.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <script>lucide.createIcons();</script>
</body>
</html>