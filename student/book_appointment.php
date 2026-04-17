<?php
session_start();
require '../db_connect.php'; 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/studentlogin.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_msg = '';

// Auto-add dental procedure column to database if it doesn't exist
try {
    $pdo->exec("ALTER TABLE appointments ADD COLUMN dental_procedure VARCHAR(100) DEFAULT NULL");
} catch (PDOException $e) {
    // Column already exists
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
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

// Handle Appointment Booking
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['service'], $_POST['appointment_date'], $_POST['appointment_time'])) {
    $service = $_POST['service'];
    $date = $_POST['appointment_date'];
    $time = $_POST['appointment_time'];
    $dental_procedure = $_POST['dental_procedure'] ?? null;
    
    // Convert service values to readable format
    $service_names = [
        'general' => 'General Consultation',
        'dental' => 'Dental Services',
        'clearance' => 'Medical Clearance',
        'counseling' => 'Mental Health Info'
    ];
    $service_type = $service_names[$service] ?? 'General Consultation';

    // Clear dental procedure if they chose a different service
    if ($service !== 'dental') {
        $dental_procedure = null;
    }

    $stmt = $pdo->prepare("INSERT INTO appointments (patient_id, service_type, dental_procedure, appointment_date, appointment_time, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
    $stmt->execute([$user_id, $service_type, $dental_procedure, $date, $time]);
    
    // Formatting text for notifications
    $proc_text = $dental_procedure ? " (" . $dental_procedure . ")" : "";

    // Notify admins about the new appointment
    $notif_msg = "[Appointment] " . $user['full_name'] . " booked an appointment for " . $service_type . $proc_text . " on " . date('M d, Y', strtotime($date)) . " at " . date('h:i A', strtotime($time)) . ".";
    $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (NULL, ?)");
    $notifStmt->execute([$notif_msg]);
    
    // Notify student that it is pending
    $stu_notif = "[Appointment] Your appointment request for " . $service_type . $proc_text . " on " . date('M d, Y', strtotime($date)) . " has been successfully submitted and is currently Pending.";
    $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")->execute([$user_id, $stu_notif]);
    
    $success_msg = "Your appointment has been booked successfully!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - MediLog</title>
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
            <div class="bg-pup-maroon text-white p-2 rounded-lg"><i data-lucide="clipboard-heart" class="h-6 w-6 text-pup-gold"></i></div>
            <span class="font-bold text-xl tracking-tight text-white">MediLog</span>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <a href="student_dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="layout-dashboard" class="h-5 w-5"></i> Dashboard</a>
            <a href="book_appointment.php" class="flex items-center gap-3 px-4 py-3 bg-pup-maroon text-white rounded-xl font-medium transition-colors shadow-sm"><i data-lucide="calendar-plus" class="h-5 w-5"></i> Book Appointment</a>
            <a href="health_records.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="folder-clock" class="h-5 w-5"></i> Health Records</a>
            <a href="medical_clearance.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="file-check-2" class="h-5 w-5"></i> Medical Clearance</a>
            <a href="#" onclick="toggleChat(); return false;" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="message-square" class="h-5 w-5"></i> Messages</a>
            <a href="student_profile.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="user" class="h-5 w-5"></i> Profile</a>
        </nav>
        <div class="p-4 border-t border-gray-800">
            <button onclick="openLogoutModal()" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-red-900/50 hover:text-red-400 rounded-xl font-medium transition-colors w-full text-left"><i data-lucide="log-out" class="h-5 w-5"></i> Sign Out</button>
        </div>
    </aside>

    <nav class="md:hidden fixed bottom-0 left-0 w-full bg-white border-t border-gray-200 z-50 overflow-x-auto no-scrollbar shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
        <div class="flex items-center w-max px-2 min-w-full justify-between">
            <a href="student_dashboard.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="layout-dashboard" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Home</span></a>
            <a href="book_appointment.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-pup-maroon transition-colors"><i data-lucide="calendar-plus" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Book</span></a>
            <a href="health_records.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="folder-clock" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Records</span></a>
            <a href="medical_clearance.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="file-check-2" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Clearance</span></a>
            <a href="#" onclick="toggleChat(); return false;" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors relative"><i data-lucide="message-square" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Messages</span></a>
            <a href="student_profile.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="user" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Profile</span></a>
        </div>
    </nav>

    <main class="flex-1 flex flex-col h-full overflow-hidden relative">
        
        <header class="bg-white border-b border-gray-200 px-4 md:px-8 py-4 flex items-center justify-between z-10 shadow-sm">
            <div class="flex items-center gap-3">
                <a href="student_dashboard.php" class="md:hidden text-gray-500 hover:text-pup-maroon">
                    <i data-lucide="arrow-left" class="h-6 w-6"></i>
                </a>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900">Book Appointment</h1>
            </div>
            
            <div class="flex items-center gap-3 sm:gap-4">
                <a href="student_notifications.php" class="text-gray-500 hover:text-pup-maroon transition-colors p-2 bg-gray-50 hover:bg-red-50 rounded-full border border-gray-200 hover:border-red-100 relative">
                    <i data-lucide="bell" class="h-5 w-5"></i>
                </a>
                <button onclick="openLogoutModal()" class="md:hidden text-gray-500 hover:text-red-600 transition-colors p-2 bg-gray-50 rounded-full border border-gray-200">
                    <i data-lucide="log-out" class="h-5 w-5"></i>
                </button>
                <div class="hidden md:flex items-center gap-3 pl-4 md:border-l md:border-gray-200">
                    <div class="text-right">
                        <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($user['full_name']) ?></p>
                    </div>
                    <img src="<?= $profile_pic ?>" alt="Profile" class="h-10 w-10 rounded-full border-2 border-gray-200 object-cover hidden md:block">
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 sm:p-8 pb-24 md:pb-8">
            <div class="max-w-3xl mx-auto">
                
                <?php if($success_msg): ?>
                    <div class="bg-green-50 text-green-700 p-4 rounded-xl text-sm font-medium border border-green-200 flex items-center gap-2 mb-6">
                        <i data-lucide="check-circle-2" class="h-5 w-5"></i> <?= htmlspecialchars($success_msg) ?>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 sm:p-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Schedule a Clinic Visit</h2>
                    
                    <form action="book_appointment.php" method="POST">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">1. Select Service</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
                            
                            <!-- General Consultation -->
                            <label class="relative cursor-pointer group">
                                <input type="radio" name="service" value="general" class="peer sr-only" checked>
                                <div class="p-5 border-2 border-gray-200 rounded-xl hover:border-gray-300 hover:bg-gray-50 transition-all duration-300 h-full peer-checked:border-pup-maroon peer-checked:bg-red-50/30 peer-checked:shadow-[0_0_15px_rgba(136,0,0,0.1)] peer-checked:[&_.radio-indicator]:border-pup-maroon peer-checked:[&_.radio-indicator>div]:scale-100 peer-checked:[&_.radio-indicator>div]:bg-pup-maroon peer-checked:[&_h3]:text-pup-maroon">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="bg-red-100 p-2 rounded-lg text-pup-maroon"><i data-lucide="stethoscope" class="h-6 w-6"></i></div>
                                        <div class="radio-indicator w-5 h-5 rounded-full border-2 border-gray-300 flex items-center justify-center transition-colors"><div class="w-2.5 h-2.5 rounded-full bg-transparent transform scale-0 transition-all duration-200"></div></div>
                                    </div>
                                    <h3 class="font-bold text-gray-900 text-lg transition-colors">General Consultation</h3>
                                    <p class="text-sm text-gray-500 mt-1">Basic check-ups, flu, fever, or non-emergency symptoms.</p>
                                </div>
                            </label>

                            <!-- Dental Services -->
                            <label class="relative cursor-pointer group">
                                <input type="radio" name="service" value="dental" class="peer sr-only">
                                <div class="p-5 border-2 border-gray-200 rounded-xl hover:border-gray-300 hover:bg-gray-50 transition-all duration-300 h-full peer-checked:border-pup-maroon peer-checked:bg-red-50/30 peer-checked:shadow-[0_0_15px_rgba(136,0,0,0.1)] peer-checked:[&_.radio-indicator]:border-pup-maroon peer-checked:[&_.radio-indicator>div]:scale-100 peer-checked:[&_.radio-indicator>div]:bg-pup-maroon peer-checked:[&_h3]:text-pup-maroon">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="bg-blue-100 p-2 rounded-lg text-blue-600"><i data-lucide="smile" class="h-6 w-6"></i></div>
                                        <div class="radio-indicator w-5 h-5 rounded-full border-2 border-gray-300 flex items-center justify-center transition-colors"><div class="w-2.5 h-2.5 rounded-full bg-transparent transform scale-0 transition-all duration-200"></div></div>
                                    </div>
                                    <h3 class="font-bold text-gray-900 text-lg transition-colors">Dental Services</h3>
                                    <p class="text-sm text-gray-500 mt-1">Tooth extraction, cleaning, and oral health checkups.</p>
                                </div>
                            </label>

                            <!-- Medical Clearance -->
                            <label class="relative cursor-pointer group">
                                <input type="radio" name="service" value="clearance" class="peer sr-only">
                                <div class="p-5 border-2 border-gray-200 rounded-xl hover:border-gray-300 hover:bg-gray-50 transition-all duration-300 h-full peer-checked:border-pup-maroon peer-checked:bg-red-50/30 peer-checked:shadow-[0_0_15px_rgba(136,0,0,0.1)] peer-checked:[&_.radio-indicator]:border-pup-maroon peer-checked:[&_.radio-indicator>div]:scale-100 peer-checked:[&_.radio-indicator>div]:bg-pup-maroon peer-checked:[&_h3]:text-pup-maroon">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="bg-yellow-100 p-2 rounded-lg text-yellow-700"><i data-lucide="file-check-2" class="h-6 w-6"></i></div>
                                        <div class="radio-indicator w-5 h-5 rounded-full border-2 border-gray-300 flex items-center justify-center transition-colors"><div class="w-2.5 h-2.5 rounded-full bg-transparent transform scale-0 transition-all duration-200"></div></div>
                                    </div>
                                    <h3 class="font-bold text-gray-900 text-lg transition-colors">Medical Clearance</h3>
                                    <p class="text-sm text-gray-500 mt-1">For OJT, enrollment, or sports participation requirements.</p>
                                </div>
                            </label>

                            <!-- Mental Health Info -->
                            <label class="relative cursor-pointer group">
                                <input type="radio" name="service" value="counseling" class="peer sr-only">
                                <div class="p-5 border-2 border-gray-200 rounded-xl hover:border-gray-300 hover:bg-gray-50 transition-all duration-300 h-full peer-checked:border-pup-maroon peer-checked:bg-red-50/30 peer-checked:shadow-[0_0_15px_rgba(136,0,0,0.1)] peer-checked:[&_.radio-indicator]:border-pup-maroon peer-checked:[&_.radio-indicator>div]:scale-100 peer-checked:[&_.radio-indicator>div]:bg-pup-maroon peer-checked:[&_h3]:text-pup-maroon">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="bg-green-100 p-2 rounded-lg text-green-600"><i data-lucide="brain-circuit" class="h-6 w-6"></i></div>
                                        <div class="radio-indicator w-5 h-5 rounded-full border-2 border-gray-300 flex items-center justify-center transition-colors"><div class="w-2.5 h-2.5 rounded-full bg-transparent transform scale-0 transition-all duration-200"></div></div>
                                    </div>
                                    <h3 class="font-bold text-gray-900 text-lg transition-colors">Mental Health Info</h3>
                                    <p class="text-sm text-gray-500 mt-1">Initial assessment or inquiry directing to guidance counselors.</p>
                                </div>
                            </label>
                        </div>

                        <!-- DYNAMIC DENTAL PROCEDURE SELECTION -->
                        <div id="dentalProcedureSection" class="hidden mb-8 bg-blue-50 border border-blue-200 rounded-xl p-6 shadow-sm relative overflow-hidden">
                            <div class="absolute top-0 left-0 w-1.5 h-full bg-blue-500"></div>
                            <label class="block text-base font-bold text-blue-900 mb-2"><i data-lucide="stethoscope" class="h-5 w-5 inline mr-1 -mt-1"></i> Select Dental Procedure <span class="text-red-500">*</span></label>
                            <p class="text-sm text-blue-700 mb-4">Please choose the specific dental service you need.</p>
                            <select name="dental_procedure" id="dental_procedure_select" class="block w-full px-4 py-3 border border-blue-300 rounded-xl focus:ring-blue-600 focus:border-blue-600 sm:text-sm bg-white font-medium text-gray-800 transition-colors cursor-pointer shadow-sm">
                                <option value="" disabled selected>Choose a procedure...</option>
                                <option value="Pasta (Filling)">Pasta (Filling)</option>
                                <option value="Bunot (Extraction)">Bunot (Extraction)</option>
                                <option value="Cleaning (Prophylaxis)">Cleaning (Prophylaxis)</option>
                                <option value="General Checkup">General Checkup</option>
                            </select>
                        </div>

                        <!-- DYNAMIC DENTAL WARNING MESSAGE -->
                        <div id="dentalWarning" class="hidden mb-8 bg-gray-50 border border-gray-200 rounded-xl p-5 flex gap-4 items-start shadow-sm transition-all">
                            <div class="bg-gray-200 text-gray-600 p-2 rounded-full flex-shrink-0"><i data-lucide="info" class="h-5 w-5"></i></div>
                            <div>
                                <h4 class="font-bold text-gray-900 text-sm mb-1">Dental Appointment Requirements</h4>
                                <ul class="text-sm text-gray-700 space-y-1 list-disc list-inside ml-1">
                                    <li>The dentist will review and approve your request before you proceed.</li>
                                    <li>All services performed by student practitioners are completely <b>free of charge</b>.</li>
                                    <li>You <b>must</b> bring your cellphone to present your confirmed online request upon arrival.</li>
                                </ul>
                            </div>
                        </div>

                        <h3 class="text-lg font-semibold text-gray-900 mb-4 pt-4 border-t border-gray-100">2. Select Date & Time</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-8">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Appointment Date</label>
                                <input type="date" name="appointment_date" class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm" required min="<?= date('Y-m-d') ?>">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Preferred Time</label>
                                <input type="time" name="appointment_time" class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm" required>
                            </div>
                        </div>

                        <div class="flex justify-end pt-4 border-t border-gray-100">
                            <button type="submit" class="bg-pup-maroon hover:bg-pup-maroonDark text-white px-8 py-3 rounded-xl font-semibold flex items-center transition-colors shadow-sm w-full sm:w-auto justify-center">
                                Confirm Booking <i data-lucide="calendar-check" class="ml-2 h-5 w-5"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Chatbox Component Included Here -->
        <?php include 'student_chatbox.php'; ?>

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

        // Dynamic Dental Workflow Logic
        const serviceRadios = document.querySelectorAll('input[name="service"]');
        const dentalWarning = document.getElementById('dentalWarning');
        const dentalProcedureSection = document.getElementById('dentalProcedureSection');
        const dentalProcedureSelect = document.getElementById('dental_procedure_select');

        serviceRadios.forEach(radio => {
            radio.addEventListener('change', (e) => {
                if (e.target.value === 'dental') {
                    // Show dental specific inputs and set requirement
                    dentalWarning.classList.remove('hidden');
                    dentalProcedureSection.classList.remove('hidden');
                    dentalProcedureSelect.required = true;
                } else {
                    // Hide dental inputs, clear and un-require
                    dentalWarning.classList.add('hidden');
                    dentalProcedureSection.classList.add('hidden');
                    dentalProcedureSelect.required = false;
                    dentalProcedureSelect.value = '';
                }
            });
        });
    </script>
</body>
</html>