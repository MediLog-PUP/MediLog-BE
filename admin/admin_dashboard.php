<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'faculty'])) {
    header("Location: ../auth/facultylogin.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT full_name, profile_pic FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Profile picture logic
$profile_pic = !empty($user['profile_pic']) && $user['profile_pic'] !== 'default.png' ? '../uploads/profiles/' . htmlspecialchars($user['profile_pic']) : 'https://ui-avatars.com/api/?name=' . urlencode($user['full_name']) . '&background=880000&color=fff';

// Fetch Dashboard Stats
$totalPatients = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$pendingClearances = $pdo->query("SELECT COUNT(*) FROM medical_clearances WHERE status = 'Pending'")->fetchColumn();
$todayAppointments = $pdo->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()")->fetchColumn();
$lowStock = $pdo->query("SELECT COUNT(*) FROM medicine_inventory WHERE status = 'Low Stock' OR quantity < 20")->fetchColumn();
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
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { pup: { maroon: '#880000', maroonDark: '#660000', gold: '#F1B500', goldLight: '#FDE68A' } }
                }
            }
        }
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

    <!-- Sidebar (Desktop) -->
    <aside class="hidden md:flex flex-col w-64 bg-gray-900 text-white h-full shadow-xl z-20 flex-shrink-0">
        <div class="p-6 flex items-center gap-3 border-b border-gray-800">
            <div class="bg-pup-gold text-gray-900 p-2 rounded-lg">
                <i data-lucide="shield-plus" class="h-6 w-6"></i>
            </div>
            <span class="font-bold text-xl tracking-tight text-white">MediLog Admin</span>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <a href="admin_dashboard.php" class="flex items-center gap-3 px-4 py-3 bg-pup-maroon text-white rounded-xl font-medium transition-colors shadow-sm"><i data-lucide="layout-dashboard" class="h-5 w-5"></i> Overview</a>
            <a href="medicine_inventory.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="pill" class="h-5 w-5"></i> Inventory</a>
            <a href="patient_records.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="users" class="h-5 w-5"></i> Patient Records</a>
            <a href="admin_appointments.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="calendar" class="h-5 w-5"></i> Appointments</a>
            <a href="admin_clearance.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors">
                <i data-lucide="file-check-2" class="h-5 w-5"></i> Clearances
                <?php if($pendingClearances > 0): ?><span class="ml-auto bg-pup-gold text-gray-900 text-xs font-bold px-2 py-0.5 rounded-full"><?= $pendingClearances ?></span><?php endif; ?>
            </a>
            <a href="admin_inquiries.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="message-square" class="h-5 w-5"></i> Inquiries</a>
            <a href="admin_profile.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="user-cog" class="h-5 w-5"></i> Profile</a>
        </nav>
        <div class="p-4 border-t border-gray-800">
            <button onclick="openLogoutModal()" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-red-900/50 hover:text-red-400 rounded-xl font-medium transition-colors w-full text-left">
                <i data-lucide="log-out" class="h-5 w-5"></i> Sign Out
            </button>
        </div>
    </aside>

    <!-- Mobile Bottom Navigation (Scrollable) -->
    <nav class="md:hidden fixed bottom-0 left-0 w-full bg-white border-t border-gray-200 z-50 overflow-x-auto no-scrollbar shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
        <div class="flex items-center w-max px-2 min-w-full justify-between">
            <a href="admin_dashboard.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-pup-maroon transition-colors">
                <i data-lucide="layout-dashboard" class="h-5 w-5"></i>
                <span class="text-[10px] font-medium mt-1">Home</span>
            </a>
            <a href="medicine_inventory.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors">
                <i data-lucide="pill" class="h-5 w-5"></i>
                <span class="text-[10px] font-medium mt-1">Inventory</span>
            </a>
            <a href="patient_records.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors">
                <i data-lucide="users" class="h-5 w-5"></i>
                <span class="text-[10px] font-medium mt-1">Patients</span>
            </a>
            <a href="admin_appointments.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors">
                <i data-lucide="calendar" class="h-5 w-5"></i>
                <span class="text-[10px] font-medium mt-1">Schedule</span>
            </a>
            <a href="admin_clearance.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors relative">
                <i data-lucide="file-check-2" class="h-5 w-5"></i>
                <span class="text-[10px] font-medium mt-1">Clearances</span>
                <?php if($pendingClearances > 0): ?><span class="absolute top-2 right-4 h-2 w-2 bg-pup-gold rounded-full"></span><?php endif; ?>
            </a>
            <a href="admin_inquiries.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors">
                <i data-lucide="message-square" class="h-5 w-5"></i>
                <span class="text-[10px] font-medium mt-1">Inquiries</span>
            </a>
            <a href="admin_profile.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors">
                <i data-lucide="user-cog" class="h-5 w-5"></i>
                <span class="text-[10px] font-medium mt-1">Profile</span>
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-full overflow-hidden">
        
        <header class="bg-white border-b border-gray-200 px-4 md:px-8 py-4 flex items-center justify-between z-10 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="md:hidden bg-pup-maroon text-white p-1.5 rounded-lg mr-2">
                    <i data-lucide="shield-plus" class="h-5 w-5 text-pup-gold"></i>
                </div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900">Admin Dashboard</h1>
            </div>
            
            <div class="flex items-center gap-3 sm:gap-4">
                <a href="admin_notifications.php" class="text-gray-500 hover:text-pup-maroon transition-colors p-2 bg-gray-50 hover:bg-red-50 rounded-full border border-gray-200 hover:border-red-100 relative">
                    <i data-lucide="bell" class="h-5 w-5"></i>
                </a>
                
                <button onclick="openLogoutModal()" class="md:hidden text-gray-500 hover:text-red-600 transition-colors p-2 bg-gray-50 rounded-full border border-gray-200">
                    <i data-lucide="log-out" class="h-5 w-5"></i>
                </button>

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
                <!-- Dynamic Stats Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex items-center gap-4">
                        <div class="bg-blue-100 text-blue-600 p-4 rounded-xl"><i data-lucide="users" class="h-6 w-6"></i></div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Registered Students</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?= $totalPatients ?></h3>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex items-center gap-4">
                        <div class="bg-yellow-100 text-yellow-600 p-4 rounded-xl"><i data-lucide="file-check-2" class="h-6 w-6"></i></div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Pending Clearances</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?= $pendingClearances ?></h3>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex items-center gap-4">
                        <div class="bg-green-100 text-green-600 p-4 rounded-xl"><i data-lucide="calendar" class="h-6 w-6"></i></div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Today's Appointments</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?= $todayAppointments ?></h3>
                        </div>
                    </div>
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 flex items-center gap-4">
                        <div class="bg-red-100 text-red-600 p-4 rounded-xl"><i data-lucide="alert-triangle" class="h-6 w-6"></i></div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Low Stock Medicine</p>
                            <h3 class="text-2xl font-bold text-gray-900"><?= $lowStock ?></h3>
                        </div>
                    </div>
                </div>
            </div>
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
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <i data-lucide="log-out" class="h-6 w-6 text-red-600"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-bold text-gray-900" id="modal-title">Sign Out</h3>
                            <div class="mt-2"><p class="text-sm text-gray-500">Are you sure you want to sign out? You will need to log in again to access the admin portal.</p></div>
                        </div>
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
        function openLogoutModal() {
            const modal = document.getElementById('logoutModal');
            const overlay = document.getElementById('logoutModalOverlay');
            const panel = document.getElementById('logoutModalPanel');
            modal.classList.remove('hidden');
            void modal.offsetWidth; 
            overlay.classList.remove('opacity-0'); overlay.classList.add('opacity-100');
            panel.classList.remove('opacity-0', 'translate-y-4', 'sm:scale-95'); panel.classList.add('opacity-100', 'translate-y-0', 'sm:scale-100');
        }
        function closeLogoutModal() {
            const modal = document.getElementById('logoutModal');
            const overlay = document.getElementById('logoutModalOverlay');
            const panel = document.getElementById('logoutModalPanel');
            overlay.classList.remove('opacity-100'); overlay.classList.add('opacity-0');
            panel.classList.remove('opacity-100', 'translate-y-0', 'sm:scale-100'); panel.classList.add('opacity-0', 'translate-y-4', 'sm:scale-95');
            setTimeout(() => modal.classList.add('hidden'), 300);
        }
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('a[href]').forEach(link => {
                link.addEventListener('click', e => {
                    const href = link.getAttribute('href');
                    if (!href || href.startsWith('#') || link.getAttribute('target') === '_blank') return;
                    e.preventDefault();
                    document.body.classList.add('page-exit');
                    setTimeout(() => window.location.href = href, 250);
                });
            });
        });
    </script>
</body>
</html>