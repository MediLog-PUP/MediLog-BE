<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'faculty', 'super_admin'])) {
    header("Location: ../auth/facultylogin.php");
    exit();
}


$user_id = $_SESSION['user_id'];

// Fetch Admin Profile
$stmt = $pdo->prepare("SELECT full_name, profile_pic FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Profile picture logic
$profile_pic = 'https://ui-avatars.com/api/?name=' . urlencode($user['full_name']) . '&background=880000&color=fff';
if (!empty($user['profile_pic']) && $user['profile_pic'] !== 'default.png') {
    if (strpos($user['profile_pic'], 'data:image') === 0) {
        $profile_pic = $user['profile_pic'];
    } else {
        $profile_pic = '../uploads/profiles/' . htmlspecialchars($user['profile_pic']);
    }
}

// Fetch Dashboard Stats
$totalPatients = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$pendingClearances = $pdo->query("SELECT COUNT(*) FROM medical_clearances WHERE status = 'Pending'")->fetchColumn();
$todayAppointments = $pdo->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()")->fetchColumn();
$lowStock = $pdo->query("SELECT COUNT(*) FROM medicine_inventory WHERE status = 'Low Stock' OR quantity <= 20")->fetchColumn();

// Fetch Recent Upcoming Appointments
$apptStmt = $pdo->query("
    SELECT a.*, u.full_name as patient_name 
    FROM appointments a 
    JOIN users u ON a.patient_id = u.id 
    WHERE a.status = 'Pending' AND a.appointment_date >= CURDATE()
    ORDER BY a.appointment_date ASC, a.appointment_time ASC 
    LIMIT 5
");
$upcoming_appointments = $apptStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Low Stock Medicines
$medStmt = $pdo->query("
    SELECT * FROM medicine_inventory 
    WHERE quantity <= 20 
    ORDER BY quantity ASC 
    LIMIT 5
");
$low_stock_medicines = $medStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MediLog</title>
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
            <a href="admin_dashboard.php" class="flex items-center gap-3 px-4 py-3 bg-pup-maroon text-white rounded-xl font-medium transition-colors shadow-sm"><i data-lucide="layout-dashboard" class="h-5 w-5"></i> Overview</a>
            <a href="medicine_inventory.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="pill" class="h-5 w-5"></i> Inventory</a>
            <a href="patient_records.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="users" class="h-5 w-5"></i> Patient Records</a>
            <a href="admin_treatment_records.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="clipboard-list" class="h-5 w-5"></i> Treatment Records</a>
            <a href="admin_appointments.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="calendar" class="h-5 w-5"></i> Appointments</a>
            <a href="admin_clearance.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="file-check-2" class="h-5 w-5"></i> Clearances</a>
            <a href="admin_inquiries.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="message-square" class="h-5 w-5"></i> Inquiries</a>
            <a href="admin_profile.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="user-cog" class="h-5 w-5"></i> Profile</a>
            <a href="super_admin_users.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="shield-alert" class="h-5 w-5"></i> Admin Management</a>
        </nav>
        <div class="p-4 border-t border-gray-800">
            <button onclick="openLogoutModal()" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-red-900/50 hover:text-red-400 rounded-xl font-medium transition-colors w-full text-left"><i data-lucide="log-out" class="h-5 w-5"></i> Sign Out</button>
        </div>
    </aside>

    <nav class="md:hidden fixed bottom-0 left-0 w-full bg-white border-t border-gray-200 z-50 overflow-x-auto no-scrollbar shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
        <div class="flex items-center w-max px-2 min-w-full justify-between">
            <a href="admin_dashboard.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-pup-maroon transition-colors"><i data-lucide="layout-dashboard" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Home</span></a>
            <a href="medicine_inventory.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="pill" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Inventory</span></a>
            <a href="patient_records.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="users" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Patients</span></a>
            <a href="admin_treatment_records.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="clipboard-list" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Treatments</span></a>
            <a href="admin_appointments.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="calendar" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Schedule</span></a>
            <a href="admin_clearance.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="file-check-2" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Clearances</span></a>
            <a href="admin_inquiries.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="message-square" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Inquiries</span></a>
            <a href="admin_profile.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="user-cog" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Profile</span></a>
            <a href="super_admin_users.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="shield-alert" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Admins</span></a>
        </div>
    </nav>

    <main class="flex-1 flex flex-col h-full overflow-hidden">
        <header class="bg-white border-b border-gray-200 px-4 sm:px-8 py-4 flex items-center justify-between z-10 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="md:hidden bg-pup-maroon text-white p-1.5 rounded-lg mr-2"><i data-lucide="shield-plus" class="h-5 w-5 text-pup-gold"></i></div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900">Dashboard Overview</h1>
            </div>
            <div class="flex items-center gap-3 sm:gap-4">
                <a href="admin_notifications.php" class="text-gray-500 hover:text-pup-maroon p-2 bg-gray-50 hover:bg-red-50 rounded-full border border-gray-200 relative">
                    <i data-lucide="bell" class="h-5 w-5"></i>
                </a>
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

        <div class="flex-1 overflow-y-auto p-4 sm:p-8 pb-24 md:pb-8">
            <div class="max-w-7xl mx-auto space-y-6">

                <!-- Stats Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex items-center gap-4 transition-transform hover:-translate-y-1">
                        <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0"><i data-lucide="users" class="h-6 w-6"></i></div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Total Patients</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?= number_format($totalPatients) ?></h3>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex items-center gap-4 transition-transform hover:-translate-y-1">
                        <div class="w-12 h-12 rounded-xl bg-green-50 text-green-600 flex items-center justify-center flex-shrink-0"><i data-lucide="calendar-check" class="h-6 w-6"></i></div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Today's Appts</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?= number_format($todayAppointments) ?></h3>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex items-center gap-4 transition-transform hover:-translate-y-1">
                        <div class="w-12 h-12 rounded-xl bg-yellow-50 text-yellow-600 flex items-center justify-center flex-shrink-0"><i data-lucide="file-clock" class="h-6 w-6"></i></div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Pending Clearance</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?= number_format($pendingClearances) ?></h3>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex items-center gap-4 transition-transform hover:-translate-y-1">
                        <div class="w-12 h-12 rounded-xl bg-red-50 text-red-600 flex items-center justify-center flex-shrink-0"><i data-lucide="alert-triangle" class="h-6 w-6"></i></div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Low Stock Alerts</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?= number_format($lowStock) ?></h3>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    
                    <!-- Upcoming Appointments Panel -->
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden flex flex-col">
                        <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                            <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2"><i data-lucide="calendar-clock" class="h-5 w-5 text-pup-maroon"></i> Upcoming Appointments</h2>
                            <a href="admin_appointments.php" class="text-sm font-semibold text-pup-maroon hover:underline">View All</a>
                        </div>
                        <div class="p-0 flex-1">
                            <?php if (count($upcoming_appointments) > 0): ?>
                                <ul class="divide-y divide-gray-100">
                                    <?php foreach ($upcoming_appointments as $appt): ?>
                                        <li class="p-6 hover:bg-gray-50 transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                            <div class="flex items-center gap-4">
                                                <div class="w-10 h-10 rounded-full bg-red-50 text-pup-maroon flex items-center justify-center font-bold text-sm border border-red-100">
                                                    <?= date('d', strtotime($appt['appointment_date'])) ?>
                                                </div>
                                                <div>
                                                    <p class="font-bold text-gray-900 text-sm"><?= htmlspecialchars($appt['patient_name']) ?></p>
                                                    <p class="text-xs text-gray-500 flex items-center gap-1 mt-1"><i data-lucide="stethoscope" class="h-3 w-3"></i> <?= htmlspecialchars($appt['service_type']) ?></p>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-sm font-medium text-gray-900"><?= date('M d, Y', strtotime($appt['appointment_date'])) ?></p>
                                                <p class="text-xs font-bold text-pup-maroon mt-1"><?= date('h:i A', strtotime($appt['appointment_time'])) ?></p>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="p-8 text-center flex flex-col items-center justify-center h-full text-gray-500">
                                    <i data-lucide="calendar-check" class="h-12 w-12 text-gray-300 mb-3"></i>
                                    <p>No pending appointments coming up.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Low Stock Alerts Panel -->
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden flex flex-col">
                        <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                            <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2"><i data-lucide="package-minus" class="h-5 w-5 text-red-600"></i> Low Stock Alerts</h2>
                            <a href="medicine_inventory.php" class="text-sm font-semibold text-pup-maroon hover:underline">Manage Inventory</a>
                        </div>
                        <div class="p-0 flex-1">
                            <?php if (count($low_stock_medicines) > 0): ?>
                                <ul class="divide-y divide-gray-100">
                                    <?php foreach ($low_stock_medicines as $med): ?>
                                        <li class="p-6 hover:bg-gray-50 transition-colors flex items-center justify-between gap-4">
                                            <div class="flex items-center gap-4">
                                                <div class="w-10 h-10 rounded-xl bg-gray-100 text-gray-500 flex items-center justify-center">
                                                    <i data-lucide="pill" class="h-5 w-5"></i>
                                                </div>
                                                <div>
                                                    <p class="font-bold text-gray-900 text-sm"><?= htmlspecialchars($med['name']) ?></p>
                                                    <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($med['category']) ?> - <?= htmlspecialchars($med['dosage']) ?></p>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-lg font-black <?= $med['quantity'] == 0 ? 'text-red-600' : 'text-yellow-600' ?>"><?= htmlspecialchars($med['quantity']) ?></p>
                                                <p class="text-[10px] uppercase font-bold text-gray-400 mt-0.5">Left in Stock</p>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="p-8 text-center flex flex-col items-center justify-center h-full text-gray-500">
                                    <i data-lucide="shield-check" class="h-12 w-12 text-green-300 mb-3"></i>
                                    <p>All medicine stocks are at healthy levels.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

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
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left"><h3 class="text-lg leading-6 font-bold text-gray-900">Sign Out</h3><div class="mt-2"><p class="text-sm text-gray-500">Are you sure you want to sign out? You will need to log in again to access the admin portal.</p></div></div>
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
            const modal = document.getElementById('logoutModal');
            modal.classList.remove('hidden');
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