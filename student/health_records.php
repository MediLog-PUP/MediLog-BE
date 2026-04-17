<?php
session_start();
require '../db_connect.php'; 

// Redirect to login if not authenticated as a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/studentlogin.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Handle Delete Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'delete_medical' && isset($_POST['record_id'])) {
        $record_id = intval($_POST['record_id']);
        
        // Verify ownership before deleting
        $verify = $pdo->prepare("SELECT id FROM health_records WHERE id = ? AND patient_id = ?");
        $verify->execute([$record_id, $user_id]);
        
        if ($verify->fetch()) {
            $del = $pdo->prepare("DELETE FROM health_records WHERE id = ?");
            if ($del->execute([$record_id])) {
                $success_msg = "General medical record deleted successfully.";
            } else {
                $error_msg = "Failed to delete the medical record.";
            }
        } else {
            $error_msg = "Unauthorized action.";
        }
    } elseif ($action === 'delete_dental' && isset($_POST['record_id'])) {
        $record_id = intval($_POST['record_id']);
        
        // Verify ownership before deleting
        $verify = $pdo->prepare("SELECT id FROM dental_evaluations WHERE id = ? AND patient_id = ?");
        $verify->execute([$record_id, $user_id]);
        
        if ($verify->fetch()) {
            $del = $pdo->prepare("DELETE FROM dental_evaluations WHERE id = ?");
            if ($del->execute([$record_id])) {
                $success_msg = "Dental record deleted successfully.";
            } else {
                $error_msg = "Failed to delete the dental record.";
            }
        } else {
            $error_msg = "Unauthorized action.";
        }
    }
}

// Auto-add new columns to health_records table if they don't exist
$new_columns = [
    'time_in' => 'TIME',
    'time_out' => 'TIME',
    'complaints' => 'TEXT',
    'treatment' => 'VARCHAR(255)',
    'quantity' => 'INT'
];
foreach ($new_columns as $col => $type) {
    try {
        $pdo->exec("ALTER TABLE health_records ADD COLUMN $col $type DEFAULT NULL");
    } catch (PDOException $e) {
        // Ignore if column already exists
    }
}

// Fetch user info for the header
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

// Fetch actual GENERAL health records for this student
$recordsStmt = $pdo->prepare("
    SELECT hr.*, 
           u.full_name as patient_name, u.course, u.section, u.dob, u.gender,
           ph.full_name as physician_name 
    FROM health_records hr 
    JOIN users u ON hr.patient_id = u.id 
    LEFT JOIN users ph ON hr.physician_id = ph.id 
    WHERE hr.patient_id = ? 
    ORDER BY hr.visit_date DESC, hr.id DESC
");
$recordsStmt->execute([$user_id]);
$records = $recordsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch DENTAL Records for this student
$dentalStmt = $pdo->prepare("
    SELECT de.*, d.full_name as dentist_name
    FROM dental_evaluations de
    LEFT JOIN users d ON de.dentist_id = d.id
    WHERE de.patient_id = ?
    ORDER BY de.created_at DESC
");
$dentalStmt->execute([$user_id]);
$dental_records = $dentalStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Records - MediLog</title>
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

    <!-- Sidebar -->
    <aside class="hidden md:flex flex-col w-64 bg-gray-900 text-white h-full shadow-xl z-20 flex-shrink-0">
        <div class="p-6 flex items-center gap-3 border-b border-gray-800">
            <div class="bg-pup-maroon text-white p-2 rounded-lg"><i data-lucide="clipboard-heart" class="h-6 w-6 text-pup-gold"></i></div>
            <span class="font-bold text-xl tracking-tight text-white">MediLog</span>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <a href="student_dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="layout-dashboard" class="h-5 w-5"></i> Dashboard</a>
            <a href="book_appointment.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="calendar-plus" class="h-5 w-5"></i> Book Appointment</a>
            <a href="health_records.php" class="flex items-center gap-3 px-4 py-3 bg-pup-maroon text-white rounded-xl font-medium transition-colors shadow-sm"><i data-lucide="folder-clock" class="h-5 w-5"></i> Health Records</a>
            <a href="medical_clearance.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="file-check-2" class="h-5 w-5"></i> Medical Clearance</a>
            <a href="#" onclick="toggleChat(); return false;" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="message-square" class="h-5 w-5"></i> Messages</a>
            <a href="student_profile.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="user" class="h-5 w-5"></i> Profile</a>
        </nav>
        <div class="p-4 border-t border-gray-800">
            <button onclick="openLogoutModal()" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-red-900/50 hover:text-red-400 rounded-xl font-medium transition-colors w-full text-left"><i data-lucide="log-out" class="h-5 w-5"></i> Sign Out</button>
        </div>
    </aside>

    <!-- Mobile Bottom Navigation -->
    <nav class="md:hidden fixed bottom-0 left-0 w-full bg-white border-t border-gray-200 z-50 overflow-x-auto no-scrollbar shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
        <div class="flex items-center w-max px-2 min-w-full justify-between">
            <a href="student_dashboard.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="layout-dashboard" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Home</span></a>
            <a href="book_appointment.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="calendar-plus" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Book</span></a>
            <a href="health_records.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-pup-maroon transition-colors"><i data-lucide="folder-clock" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Records</span></a>
            <a href="medical_clearance.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="file-check-2" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Clearance</span></a>
            <a href="#" onclick="toggleChat(); return false;" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors relative"><i data-lucide="message-square" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Messages</span></a>
            <a href="student_profile.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="user" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Profile</span></a>
        </div>
    </nav>

    <main class="flex-1 flex flex-col h-full overflow-hidden relative">
        <header class="bg-white border-b border-gray-200 px-4 md:px-8 py-4 flex items-center justify-between z-10 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="md:hidden bg-pup-maroon text-white p-1.5 rounded-lg mr-2"><i data-lucide="clipboard-heart" class="h-5 w-5 text-pup-gold"></i></div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900">Health Records</h1>
            </div>
            <div class="flex items-center gap-3 sm:gap-4">
                <a href="student_notifications.php" class="text-gray-500 hover:text-pup-maroon p-2 bg-gray-50 hover:bg-red-50 rounded-full border border-gray-200 relative"><i data-lucide="bell" class="h-5 w-5"></i></a>
                <button onclick="openLogoutModal()" class="md:hidden text-gray-500 hover:text-red-600 transition-colors p-2 bg-gray-50 rounded-full border border-gray-200"><i data-lucide="log-out" class="h-5 w-5"></i></button>
                <div class="hidden md:flex items-center gap-3 pl-4 border-l border-gray-200">
                    <div class="text-right">
                        <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($user['full_name']) ?></p>
                    </div>
                    <img src="<?= $profile_pic ?>" alt="Profile" class="h-10 w-10 rounded-full border-2 border-gray-200 object-cover hidden md:block">
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 sm:p-8 pb-24 md:pb-8">
            <div class="max-w-[100rem] mx-auto space-y-6">
                
                <?php if($success_msg): ?>
                    <div class="bg-green-50 text-green-700 p-4 rounded-xl text-sm font-bold border border-green-200 flex items-center gap-2"><i data-lucide="check-circle-2" class="h-5 w-5"></i> <?= htmlspecialchars($success_msg) ?></div>
                <?php endif; ?>
                <?php if($error_msg): ?>
                    <div class="bg-red-50 text-red-700 p-4 rounded-xl text-sm font-bold border border-red-200 flex items-start gap-2">
                        <i data-lucide="alert-circle" class="h-5 w-5 mt-0.5 flex-shrink-0"></i> 
                        <div class="break-words"><?= htmlspecialchars($error_msg) ?></div>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 mb-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2"><i data-lucide="folder-open" class="h-6 w-6 text-pup-maroon"></i> My Clinic History</h2>

                    <!-- TABS NAVIGATION -->
                    <div class="border-b border-gray-200 mb-6">
                        <nav class="-mb-px flex gap-6" aria-label="Tabs">
                            <button onclick="switchTab('medical')" id="tab-medical" class="tab-btn border-pup-maroon text-pup-maroon whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2 transition-colors">
                                General Medical Records
                                <span class="bg-red-100 text-red-800 py-0.5 px-2 rounded-full text-xs font-bold"><?= count($records) ?></span>
                            </button>
                            <button onclick="switchTab('dental')" id="tab-dental" class="tab-btn border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2 transition-colors">
                                Dental Records
                                <span class="bg-blue-100 text-blue-800 py-0.5 px-2 rounded-full text-xs font-bold"><?= count($dental_records) ?></span>
                            </button>
                        </nav>
                    </div>

                    <!-- Medical Tab -->
                    <div id="content-medical" class="tab-content">
                        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse whitespace-nowrap">
                                    <thead>
                                        <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider border-b border-gray-200">
                                            <th class="p-4 font-semibold">Date</th>
                                            <th class="p-4 font-semibold">Time In</th>
                                            <th class="p-4 font-semibold">Time Out</th>
                                            <th class="p-4 font-semibold">Student Name</th>
                                            <th class="p-4 font-semibold">Course & Sec</th>
                                            <th class="p-4 font-semibold">Age</th>
                                            <th class="p-4 font-semibold">Gender</th>
                                            <th class="p-4 font-semibold">Complaints / Reason</th>
                                            <th class="p-4 font-semibold">Treatment / Medicines</th>
                                            <th class="p-4 font-semibold text-center">Qty</th>
                                            <th class="p-4 font-semibold">Physician</th>
                                            <th class="p-4 font-semibold text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 text-sm">
                                        <?php if(count($records) > 0): ?>
                                            <?php foreach($records as $hr): 
                                                $age = 'N/A';
                                                if (!empty($hr['dob'])) {
                                                    $dob = new DateTime($hr['dob']);
                                                    $now = new DateTime();
                                                    $age = $now->diff($dob)->y;
                                                }
                                            ?>
                                                <tr class="hover:bg-gray-50 transition-colors">
                                                    <td class="p-4 text-gray-900 font-medium"><?= date("M d, Y", strtotime($hr['visit_date'])) ?></td>
                                                    <td class="p-4 text-gray-600 font-semibold"><?= $hr['time_in'] ? date("h:i A", strtotime($hr['time_in'])) : '<span class="text-gray-400 italic font-normal">--:--</span>' ?></td>
                                                    <td class="p-4 text-gray-600 font-semibold"><?= $hr['time_out'] ? date("h:i A", strtotime($hr['time_out'])) : '<span class="text-gray-400 italic font-normal">--:--</span>' ?></td>
                                                    <td class="p-4 font-bold text-pup-maroon"><?= htmlspecialchars($hr['patient_name']) ?></td>
                                                    <td class="p-4 text-gray-600"><?= htmlspecialchars($hr['course'] . ' ' . $hr['section']) ?></td>
                                                    <td class="p-4 text-gray-600"><?= $age ?></td>
                                                    <td class="p-4 text-gray-600"><?= htmlspecialchars($hr['gender'] ?? 'N/A') ?></td>
                                                    <td class="p-4 text-gray-600 max-w-[150px] truncate" title="<?= htmlspecialchars($hr['complaints'] ?? $hr['service_reason']) ?>"><?= htmlspecialchars($hr['complaints'] ?? $hr['service_reason']) ?></td>
                                                    <td class="p-4 text-gray-600 max-w-[150px] truncate" title="<?= htmlspecialchars($hr['treatment'] ?? 'N/A') ?>"><?= htmlspecialchars($hr['treatment'] ?? 'N/A') ?></td>
                                                    <td class="p-4 text-gray-900 font-bold text-center"><?= htmlspecialchars($hr['quantity'] ?? '-') ?></td>
                                                    <td class="p-4 text-gray-600"><?= htmlspecialchars($hr['physician_name'] ?? 'Clinic Staff') ?></td>
                                                    <td class="p-4 text-right">
                                                        <div class="flex items-center justify-end gap-2">
                                                            <button onclick="openViewMedicalModal(this)" data-record='<?= htmlspecialchars(json_encode($hr), ENT_QUOTES, 'UTF-8') ?>' class="p-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors shadow-sm" title="View Full Record">
                                                                <i data-lucide="eye" class="h-4 w-4"></i>
                                                            </button>
                                                            <button onclick="openDeleteMedicalModal(<?= $hr['id'] ?>)" class="p-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors shadow-sm" title="Delete Record">
                                                                <i data-lucide="trash-2" class="h-4 w-4"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="12" class="p-8 text-center text-gray-500">You have no recorded general medical visits.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Dental Tab -->
                    <div id="content-dental" class="tab-content hidden">
                        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse whitespace-nowrap">
                                    <thead>
                                        <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider border-b border-gray-200">
                                            <th class="p-4 font-semibold">Date Recorded</th>
                                            <th class="p-4 font-semibold">Dentist</th>
                                            <th class="p-4 font-semibold">Procedure Performed</th>
                                            <th class="p-4 font-semibold">Dentist's Evaluation & Remarks</th>
                                            <th class="p-4 font-semibold text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 text-sm">
                                        <?php if(count($dental_records) > 0): ?>
                                            <?php foreach($dental_records as $dr): ?>
                                                <tr class="hover:bg-gray-50 transition-colors">
                                                    <td class="p-4 text-gray-900 font-medium"><?= date("M d, Y h:i A", strtotime($dr['created_at'])) ?></td>
                                                    <td class="p-4 text-gray-600 font-semibold">Dr. <?= htmlspecialchars($dr['dentist_name'] ?? 'Unknown') ?></td>
                                                    <td class="p-4 font-bold text-pup-maroon"><?= htmlspecialchars($dr['procedure_done']) ?></td>
                                                    <td class="p-4 text-gray-600 max-w-[200px] truncate leading-relaxed"><?= htmlspecialchars($dr['evaluation_notes']) ?></td>
                                                    <td class="p-4 text-right">
                                                        <div class="flex items-center justify-end gap-2">
                                                            <button onclick="openViewDentalModal(this)" data-record='<?= htmlspecialchars(json_encode($dr), ENT_QUOTES, 'UTF-8') ?>' class="p-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors shadow-sm" title="View Full Record">
                                                                <i data-lucide="eye" class="h-4 w-4"></i>
                                                            </button>
                                                            <button onclick="openDeleteDentalModal(<?= $dr['id'] ?>)" class="p-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors shadow-sm" title="Delete Record">
                                                                <i data-lucide="trash-2" class="h-4 w-4"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="5" class="p-8 text-center text-gray-500">You have no recorded dental procedures yet.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>

        <!-- Chatbox Component Included Here -->
        <?php include 'student_chatbox.php'; ?>

    </main>

    <!-- View Medical Record Modal -->
    <div id="viewMedicalModal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div id="viewMedicalModalOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity opacity-0 duration-300" aria-hidden="true" onclick="closeViewMedicalModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div id="viewMedicalModalPanel" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95 duration-300">
                <div class="bg-white px-6 pt-6 pb-6 border-b border-gray-100">
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2"><i data-lucide="file-text" class="h-5 w-5 text-pup-maroon"></i> Medical Record Details</h3>
                        <button type="button" onclick="closeViewMedicalModal()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-6 w-6"></i></button>
                    </div>
                    <div class="space-y-4 text-sm">
                        <div class="grid grid-cols-2 gap-4 bg-gray-50 p-4 rounded-xl border border-gray-100">
                            <div><p class="text-xs text-gray-500 font-semibold mb-1 uppercase tracking-wider">Date of Visit</p><p class="font-bold text-gray-900" id="vm-date"></p></div>
                            <div><p class="text-xs text-gray-500 font-semibold mb-1 uppercase tracking-wider">Physician / Faculty</p><p class="font-bold text-gray-900" id="vm-physician"></p></div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 px-1">
                            <div><p class="text-xs text-gray-500 font-semibold mb-1 uppercase tracking-wider">Time In</p><p class="font-medium text-gray-900" id="vm-time-in"></p></div>
                            <div><p class="text-xs text-gray-500 font-semibold mb-1 uppercase tracking-wider">Time Out</p><p class="font-medium text-gray-900" id="vm-time-out"></p></div>
                        </div>
                        <div class="border-t border-gray-100 pt-4 px-1">
                            <p class="text-xs text-gray-500 font-semibold mb-1 uppercase tracking-wider">Complaints / Reason</p>
                            <p class="font-medium text-gray-900 whitespace-pre-wrap" id="vm-complaints"></p>
                        </div>
                        <div class="border-t border-gray-100 pt-4 px-1">
                            <p class="text-xs text-gray-500 font-semibold mb-1 uppercase tracking-wider">Treatment / Medicines Given</p>
                            <p class="font-medium text-gray-900 whitespace-pre-wrap" id="vm-treatment"></p>
                        </div>
                        <div class="grid grid-cols-2 gap-4 border-t border-gray-100 pt-4 px-1">
                            <div><p class="text-xs text-gray-500 font-semibold mb-1 uppercase tracking-wider">Quantity Dispensed</p><p class="font-medium text-gray-900" id="vm-quantity"></p></div>
                            <div><p class="text-xs text-gray-500 font-semibold mb-1 uppercase tracking-wider">Status</p><p class="font-bold text-green-600" id="vm-status"></p></div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-6 py-4 flex justify-end">
                    <button type="button" onclick="closeViewMedicalModal()" class="inline-flex justify-center rounded-xl border border-gray-300 shadow-sm px-6 py-2.5 bg-white text-sm font-bold text-gray-700 hover:bg-gray-50 transition-colors">Close Details</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Medical Record Modal -->
    <div id="deleteMedicalModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div id="deleteMedicalModalOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity opacity-0 duration-300" onclick="closeDeleteMedicalModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div id="deleteMedicalModalPanel" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-sm w-full opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95 duration-300">
                <form action="health_records.php" method="POST">
                    <input type="hidden" name="action" value="delete_medical">
                    <input type="hidden" name="record_id" id="delete-medical-id">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10"><i data-lucide="alert-triangle" class="h-6 w-6 text-red-600"></i></div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-bold text-gray-900">Delete Medical Record</h3>
                                <div class="mt-2"><p class="text-sm text-gray-500">Are you sure you want to permanently delete this medical record? This action cannot be undone.</p></div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 flex flex-col sm:flex-row-reverse gap-2">
                        <button type="submit" class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-bold text-white hover:bg-red-700 sm:w-auto sm:text-sm transition-colors text-center">Delete</button>
                        <button type="button" onclick="closeDeleteMedicalModal()" class="w-full inline-flex justify-center rounded-xl border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-bold text-gray-700 hover:bg-gray-50 sm:w-auto sm:text-sm transition-colors">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Dental Record Modal -->
    <div id="viewDentalModal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div id="viewDentalModalOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity opacity-0 duration-300" aria-hidden="true" onclick="closeViewDentalModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div id="viewDentalModalPanel" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95 duration-300">
                <div class="bg-white px-6 pt-6 pb-6 border-b border-gray-100">
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2"><i data-lucide="smile" class="h-5 w-5 text-blue-600"></i> Dental Record Details</h3>
                        <button type="button" onclick="closeViewDentalModal()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-6 w-6"></i></button>
                    </div>
                    <div class="space-y-4 text-sm">
                        <div class="grid grid-cols-2 gap-4 bg-gray-50 p-4 rounded-xl border border-gray-100">
                            <div><p class="text-xs text-gray-500 font-semibold mb-1 uppercase tracking-wider">Date Recorded</p><p class="font-bold text-gray-900" id="vd-date"></p></div>
                            <div><p class="text-xs text-gray-500 font-semibold mb-1 uppercase tracking-wider">Attending Dentist</p><p class="font-bold text-gray-900" id="vd-dentist"></p></div>
                        </div>
                        <div class="border-t border-gray-100 pt-4 px-1">
                            <p class="text-xs text-gray-500 font-semibold mb-1 uppercase tracking-wider">Procedure Performed</p>
                            <p class="font-bold text-pup-maroon text-base" id="vd-procedure"></p>
                        </div>
                        <div class="border-t border-gray-100 pt-4 px-1">
                            <p class="text-xs text-gray-500 font-semibold mb-1 uppercase tracking-wider">Dentist's Evaluation & Remarks</p>
                            <p class="font-medium text-gray-900 whitespace-pre-wrap leading-relaxed bg-blue-50 p-4 rounded-xl border border-blue-100" id="vd-remarks"></p>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-6 py-4 flex justify-end">
                    <button type="button" onclick="closeViewDentalModal()" class="inline-flex justify-center rounded-xl border border-gray-300 shadow-sm px-6 py-2.5 bg-white text-sm font-bold text-gray-700 hover:bg-gray-50 transition-colors">Close Details</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Dental Record Modal -->
    <div id="deleteDentalModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div id="deleteDentalModalOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity opacity-0 duration-300" onclick="closeDeleteDentalModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div id="deleteDentalModalPanel" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-sm w-full opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95 duration-300">
                <form action="health_records.php" method="POST">
                    <input type="hidden" name="action" value="delete_dental">
                    <input type="hidden" name="record_id" id="delete-dental-id">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10"><i data-lucide="alert-triangle" class="h-6 w-6 text-red-600"></i></div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-bold text-gray-900">Delete Dental Record</h3>
                                <div class="mt-2"><p class="text-sm text-gray-500">Are you sure you want to permanently delete this dental record? This action cannot be undone.</p></div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 flex flex-col sm:flex-row-reverse gap-2">
                        <button type="submit" class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-bold text-white hover:bg-red-700 sm:w-auto sm:text-sm transition-colors text-center">Delete</button>
                        <button type="button" onclick="closeDeleteDentalModal()" class="w-full inline-flex justify-center rounded-xl border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-bold text-gray-700 hover:bg-gray-50 sm:w-auto sm:text-sm transition-colors">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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
        
        // Tab Logic
        function switchTab(tabId) {
            localStorage.setItem('activeHrTab', tabId);

            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            
            document.querySelectorAll('.tab-btn').forEach(el => {
                el.classList.remove('border-pup-maroon', 'text-pup-maroon');
                el.classList.add('border-transparent', 'text-gray-500');
            });

            document.getElementById('content-' + tabId).classList.remove('hidden');
            
            const activeBtn = document.getElementById('tab-' + tabId);
            activeBtn.classList.remove('border-transparent', 'text-gray-500');
            activeBtn.classList.add('border-pup-maroon', 'text-pup-maroon');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const savedTab = localStorage.getItem('activeHrTab') || 'medical';
            switchTab(savedTab);
        });

        // View Modals Logic
        function openViewMedicalModal(btn) {
            const data = JSON.parse(btn.getAttribute('data-record'));
            
            const dateObj = new Date(data.visit_date);
            document.getElementById('vm-date').innerText = dateObj.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
            document.getElementById('vm-physician').innerText = data.physician_name || 'Clinic Staff';
            
            const formatTime = (timeStr) => {
                if (!timeStr) return '--:--';
                const [hours, minutes] = timeStr.split(':');
                const h = parseInt(hours, 10);
                const ampm = h >= 12 ? 'PM' : 'AM';
                return `${h % 12 || 12}:${minutes} ${ampm}`;
            };

            document.getElementById('vm-time-in').innerText = formatTime(data.time_in);
            document.getElementById('vm-time-out').innerText = formatTime(data.time_out);
            document.getElementById('vm-complaints').innerText = data.complaints || data.service_reason || 'N/A';
            document.getElementById('vm-treatment').innerText = data.treatment || 'N/A';
            document.getElementById('vm-quantity').innerText = data.quantity || '-';
            document.getElementById('vm-status').innerText = data.status || 'Completed';

            document.getElementById('viewMedicalModal').classList.remove('hidden');
            setTimeout(() => {
                document.getElementById('viewMedicalModalOverlay').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('viewMedicalModalPanel').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('viewMedicalModalPanel').classList.replace('translate-y-4', 'translate-y-0');
                document.getElementById('viewMedicalModalPanel').classList.replace('sm:scale-95', 'sm:scale-100');
            }, 10);
        }

        function closeViewMedicalModal() {
            document.getElementById('viewMedicalModalOverlay').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('viewMedicalModalPanel').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('viewMedicalModalPanel').classList.replace('translate-y-0', 'translate-y-4');
            document.getElementById('viewMedicalModalPanel').classList.replace('sm:scale-100', 'sm:scale-95');
            setTimeout(() => document.getElementById('viewMedicalModal').classList.add('hidden'), 300);
        }

        // Delete Medical Logic
        function openDeleteMedicalModal(id) {
            document.getElementById('delete-medical-id').value = id;
            document.getElementById('deleteMedicalModal').classList.remove('hidden');
            setTimeout(() => {
                document.getElementById('deleteMedicalModalOverlay').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('deleteMedicalModalPanel').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('deleteMedicalModalPanel').classList.replace('translate-y-4', 'translate-y-0');
                document.getElementById('deleteMedicalModalPanel').classList.replace('sm:scale-95', 'sm:scale-100');
            }, 10);
        }

        function closeDeleteMedicalModal() {
            document.getElementById('deleteMedicalModalOverlay').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('deleteMedicalModalPanel').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('deleteMedicalModalPanel').classList.replace('translate-y-0', 'translate-y-4');
            document.getElementById('deleteMedicalModalPanel').classList.replace('sm:scale-100', 'sm:scale-95');
            setTimeout(() => document.getElementById('deleteMedicalModal').classList.add('hidden'), 300);
        }

        function openViewDentalModal(btn) {
            const data = JSON.parse(btn.getAttribute('data-record'));
            
            const dateObj = new Date(data.created_at);
            document.getElementById('vd-date').innerText = dateObj.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: 'numeric' });
            document.getElementById('vd-dentist').innerText = 'Dr. ' + (data.dentist_name || 'Unknown');
            document.getElementById('vd-procedure').innerText = data.procedure_done || 'N/A';
            document.getElementById('vd-remarks').innerText = data.evaluation_notes || 'N/A';

            document.getElementById('viewDentalModal').classList.remove('hidden');
            setTimeout(() => {
                document.getElementById('viewDentalModalOverlay').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('viewDentalModalPanel').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('viewDentalModalPanel').classList.replace('translate-y-4', 'translate-y-0');
                document.getElementById('viewDentalModalPanel').classList.replace('sm:scale-95', 'sm:scale-100');
            }, 10);
        }

        function closeViewDentalModal() {
            document.getElementById('viewDentalModalOverlay').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('viewDentalModalPanel').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('viewDentalModalPanel').classList.replace('translate-y-0', 'translate-y-4');
            document.getElementById('viewDentalModalPanel').classList.replace('sm:scale-100', 'sm:scale-95');
            setTimeout(() => document.getElementById('viewDentalModal').classList.add('hidden'), 300);
        }

        // Delete Dental Logic
        function openDeleteDentalModal(id) {
            document.getElementById('delete-dental-id').value = id;
            document.getElementById('deleteDentalModal').classList.remove('hidden');
            setTimeout(() => {
                document.getElementById('deleteDentalModalOverlay').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('deleteDentalModalPanel').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('deleteDentalModalPanel').classList.replace('translate-y-4', 'translate-y-0');
                document.getElementById('deleteDentalModalPanel').classList.replace('sm:scale-95', 'sm:scale-100');
            }, 10);
        }

        function closeDeleteDentalModal() {
            document.getElementById('deleteDentalModalOverlay').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('deleteDentalModalPanel').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('deleteDentalModalPanel').classList.replace('translate-y-0', 'translate-y-4');
            document.getElementById('deleteDentalModalPanel').classList.replace('sm:scale-100', 'sm:scale-95');
            setTimeout(() => document.getElementById('deleteDentalModal').classList.add('hidden'), 300);
        }
    </script>
</body>
</html>