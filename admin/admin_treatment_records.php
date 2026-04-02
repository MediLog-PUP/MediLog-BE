<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'faculty'])) {
    header("Location: ../auth/facultylogin.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

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

// Handle Record Updates (Add / Edit / Delete)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'add_record') {
        $patient_id = intval($_POST['patient_id']);
        $visit_date = $_POST['visit_date'];
        $time_in = !empty($_POST['time_in']) ? $_POST['time_in'] : null;
        $time_out = !empty($_POST['time_out']) ? $_POST['time_out'] : null;
        $complaints = $_POST['complaints'] ?? '';
        $medicine_id = !empty($_POST['medicine_id']) ? intval($_POST['medicine_id']) : null;
        $quantity = !empty($_POST['quantity']) ? intval($_POST['quantity']) : null;
        $other_treatment = $_POST['other_treatment'] ?? '';

        $treatment_text = $other_treatment;

        // Handle Inventory Deduction
        if ($medicine_id && $quantity > 0) {
            $medStmt = $pdo->prepare("SELECT name, quantity FROM medicine_inventory WHERE id = ?");
            $medStmt->execute([$medicine_id]);
            $med = $medStmt->fetch();

            if ($med && $med['quantity'] >= $quantity) {
                // Deduct stock safely
                $new_qty = $med['quantity'] - $quantity;
                $new_status = ($new_qty > 20) ? 'In Stock' : (($new_qty > 0) ? 'Low Stock' : 'Out of Stock');
                $pdo->prepare("UPDATE medicine_inventory SET quantity=?, status=? WHERE id=?")->execute([$new_qty, $new_status, $medicine_id]);

                // Construct Treatment string
                $med_string = $med['name'];
                $treatment_text = empty($treatment_text) ? $med_string : $med_string . " + " . $treatment_text;
                
                // Log inventory reduction
                $adminName = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
                $adminName->execute([$user_id]);
                $admin_full_name = $adminName->fetchColumn();
                
                $notif_msg = "[Inventory] Faculty " . $admin_full_name . " dispensed " . $quantity . " pcs of " . $med['name'] . " for a treatment.";
                $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (NULL, ?)")->execute([$notif_msg]);
            } else {
                $error_msg = "Insufficient stock for the selected medicine.";
            }
        }

        if (empty($error_msg)) {
            // Save as Physician/Faculty action
            $stmt = $pdo->prepare("INSERT INTO health_records (patient_id, physician_id, visit_date, time_in, time_out, service_reason, complaints, treatment, quantity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Completed')");
            $stmt->execute([$patient_id, $user_id, $visit_date, $time_in, $time_out, 'Clinic Visit', $complaints, $treatment_text, $quantity]);
            $success_msg = "New treatment record added successfully and linked to the student.";
        }
        
    } elseif ($_POST['action'] === 'edit_record') {
        $hr_id = intval($_POST['record_id']);
        $time_in = !empty($_POST['time_in']) ? $_POST['time_in'] : null;
        $time_out = !empty($_POST['time_out']) ? $_POST['time_out'] : null;
        $complaints = !empty($_POST['complaints']) ? $_POST['complaints'] : null;
        $treatment = !empty($_POST['treatment']) ? $_POST['treatment'] : null;
        $quantity = $_POST['quantity'] !== '' ? intval($_POST['quantity']) : null;

        $stmt = $pdo->prepare("UPDATE health_records SET time_in=?, time_out=?, complaints=?, treatment=?, quantity=? WHERE id=?");
        if($stmt->execute([$time_in, $time_out, $complaints, $treatment, $quantity, $hr_id])) {
            $success_msg = "Treatment record updated successfully.";
        } else {
            $error_msg = "Failed to update the treatment record.";
        }
    } elseif ($_POST['action'] === 'delete_record') {
        $hr_id = intval($_POST['record_id']);
        if($pdo->prepare("DELETE FROM health_records WHERE id=?")->execute([$hr_id])) {
            $success_msg = "Treatment record deleted successfully.";
        } else {
            $error_msg = "Failed to delete the treatment record.";
        }
    }
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

// Fetch Students for Add Record Dropdown
$studentsListStmt = $pdo->query("SELECT id, full_name, dob, gender FROM users WHERE role='student' ORDER BY full_name ASC");
$students_list = $studentsListStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Medicines for Add Record Dropdown
$medsListStmt = $pdo->query("SELECT id, name, quantity FROM medicine_inventory WHERE quantity > 0 ORDER BY name ASC");
$meds_list = $medsListStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch All Treatment Records
try {
    $recordsStmt = $pdo->query("
        SELECT hr.*, 
               u.full_name as patient_name, u.course, u.section, u.dob, u.gender,
               ph.full_name as physician_name 
        FROM health_records hr 
        JOIN users u ON hr.patient_id = u.id 
        LEFT JOIN users ph ON hr.physician_id = ph.id 
        ORDER BY hr.visit_date DESC, hr.id DESC
    ");
    $records = $recordsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error fetching records: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Treatment Records - MediLog</title>
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

    <!-- Include the global loading screen -->
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
            <a href="admin_treatment_records.php" class="flex items-center gap-3 px-4 py-3 bg-pup-maroon text-white rounded-xl font-medium transition-colors shadow-sm"><i data-lucide="clipboard-list" class="h-5 w-5"></i> Treatment Records</a>
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
            <a href="admin_dashboard.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="layout-dashboard" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Home</span></a>
            <a href="medicine_inventory.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="pill" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Inventory</span></a>
            <a href="patient_records.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="users" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Patients</span></a>
            <a href="admin_treatment_records.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-pup-maroon transition-colors"><i data-lucide="clipboard-list" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Treatments</span></a>
            <a href="admin_appointments.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="calendar" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Schedule</span></a>
            <a href="admin_clearance.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="file-check-2" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Clearances</span></a>
            <a href="admin_inquiries.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="message-square" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Inquiries</span></a>
            <a href="admin_profile.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="user-cog" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Profile</span></a>
            <a href="super_admin_users.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="shield-alert" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Admins</span></a>
        </div>
    </nav>

    <main class="flex-1 flex flex-col h-full overflow-hidden">
        <header class="bg-white border-b border-gray-200 px-4 sm:px-8 py-4 flex items-center justify-between shadow-sm z-10">
            <div class="flex items-center gap-3">
                <div class="md:hidden bg-pup-maroon text-white p-1.5 rounded-lg mr-2"><i data-lucide="shield-plus" class="h-5 w-5 text-pup-gold"></i></div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900">Treatment Records</h1>
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
            <div class="max-w-full mx-auto space-y-6">
                
                <?php if($success_msg): ?>
                    <div class="bg-green-50 text-green-700 p-4 rounded-xl text-sm font-medium border border-green-200 flex items-center gap-2">
                        <i data-lucide="check-circle-2" class="h-5 w-5"></i> <?= htmlspecialchars($success_msg) ?>
                    </div>
                <?php endif; ?>
                <?php if($error_msg): ?>
                    <div class="bg-red-50 text-red-700 p-4 rounded-xl text-sm font-bold border border-red-200 flex items-center gap-2">
                        <i data-lucide="alert-circle" class="h-5 w-5"></i> <?= htmlspecialchars($error_msg) ?>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                        <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2"><i data-lucide="clipboard-list" class="h-5 w-5 text-pup-maroon"></i> All Treatment Logs</h2>
                        <button onclick="openAddRecordModal()" class="bg-pup-maroon hover:bg-pup-maroonDark text-white px-4 py-2.5 rounded-xl text-sm font-semibold shadow-sm flex items-center gap-2 transition-colors"><i data-lucide="plus" class="h-4 w-4"></i> Add Record</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse whitespace-nowrap">
                            <thead>
                                <tr class="bg-white text-gray-500 text-xs uppercase tracking-wider border-b border-gray-200">
                                    <th class="p-4 font-semibold">Date</th>
                                    <th class="p-4 font-semibold">Time In</th>
                                    <th class="p-4 font-semibold">Time Out</th>
                                    <th class="p-4 font-semibold">Student Name</th>
                                    <th class="p-4 font-semibold">Course, Year & Section</th>
                                    <th class="p-4 font-semibold">Age</th>
                                    <th class="p-4 font-semibold">Gender</th>
                                    <th class="p-4 font-semibold">Complaints</th>
                                    <th class="p-4 font-semibold">Treatment / Medicines</th>
                                    <th class="p-4 font-semibold text-center">Quantity</th>
                                    <th class="p-4 font-semibold">Faculty / Physician</th>
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
                                                    <button onclick="openEditRecordModal(this)" data-record='<?= htmlspecialchars(json_encode($hr), ENT_QUOTES, 'UTF-8') ?>' class="p-2 bg-yellow-50 text-yellow-600 rounded-lg hover:bg-yellow-100 transition-colors" title="Edit Treatment Details"><i data-lucide="edit-2" class="h-4 w-4"></i></button>
                                                    <button onclick="openDeleteRecordModal(<?= $hr['id'] ?>)" class="p-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors" title="Delete Record"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="12" class="p-8 text-center text-gray-500">No treatment records found in the system.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Add Record Modal -->
    <div id="addRecordModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div id="addRecordOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity opacity-0 duration-300" onclick="closeAddRecordModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div id="addRecordPanel" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95 duration-300">
                <form action="admin_treatment_records.php" method="POST" class="flex flex-col max-h-[90vh]">
                    <input type="hidden" name="action" value="add_record">
                    <div class="bg-white px-6 pt-6 pb-4 border-b border-gray-100 flex-shrink-0">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xl font-bold text-gray-900">Add New Treatment</h3>
                            <button type="button" onclick="closeAddRecordModal()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-6 w-6"></i></button>
                        </div>
                    </div>
                    
                    <div class="px-6 py-6 overflow-y-auto space-y-4 bg-white flex-1">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                                <input type="date" name="visit_date" value="<?= date('Y-m-d') ?>" required class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Select Student <span class="text-red-500">*</span></label>
                                <select name="patient_id" id="add-student-select" required class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm bg-white" onchange="handleStudentSelect()">
                                    <option value="" disabled selected>Choose student...</option>
                                    <?php foreach($students_list as $s): ?>
                                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Auto-filled demographics -->
                        <div class="grid grid-cols-2 gap-4 bg-gray-50 p-4 rounded-xl border border-gray-100">
                            <div>
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Auto-Calculated Age</p>
                                <p class="text-sm font-bold text-gray-900" id="add-student-age">--</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Registered Gender</p>
                                <p class="text-sm font-bold text-gray-900" id="add-student-gender">--</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Time In</label>
                                <input type="time" name="time_in" class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Time Out</label>
                                <input type="time" name="time_out" class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Complaints / Reason</label>
                            <textarea name="complaints" rows="2" placeholder="Describe the student's symptoms..." class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm"></textarea>
                        </div>

                        <div class="border-t border-gray-100 pt-4">
                            <h4 class="text-sm font-bold text-gray-900 mb-3">Treatment Details</h4>
                            <div class="grid grid-cols-3 gap-4 mb-4">
                                <div class="col-span-2">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Select Inventory Medicine (Optional)</label>
                                    <select name="medicine_id" id="add-med-select" class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm bg-white" onchange="handleMedSelect()">
                                        <option value="">No medicine dispensed</option>
                                        <?php foreach($meds_list as $m): ?>
                                            <option value="<?= $m['id'] ?>" data-qty="<?= $m['quantity'] ?>"><?= htmlspecialchars($m['name']) ?> (Stock: <?= $m['quantity'] ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Dispense Qty</label>
                                    <input type="number" name="quantity" id="add-med-qty" min="1" disabled placeholder="0" class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm disabled:bg-gray-100 disabled:cursor-not-allowed">
                                    <p class="text-[10px] text-gray-400 mt-1">Will deduct stock</p>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Other Treatment / Remarks</label>
                                <input type="text" name="other_treatment" placeholder="e.g. Rest, Ice pack applied..." class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 px-6 py-4 flex flex-col sm:flex-row-reverse gap-3 flex-shrink-0 rounded-b-2xl border-t border-gray-100">
                        <button type="submit" class="w-full sm:w-auto inline-flex justify-center rounded-xl shadow-sm px-6 py-2.5 bg-pup-maroon text-sm font-bold text-white hover:bg-pup-maroonDark transition-colors">Save Treatment Record</button>
                        <button type="button" onclick="closeAddRecordModal()" class="w-full sm:w-auto inline-flex justify-center rounded-xl border border-gray-300 shadow-sm px-6 py-2.5 bg-white text-sm font-bold text-gray-700 hover:bg-gray-50 transition-colors">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Record Modal -->
    <div id="editRecordModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div id="editRecordOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity opacity-0 duration-300" onclick="closeEditRecordModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div id="editRecordPanel" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95 duration-300">
                <form action="admin_treatment_records.php" method="POST">
                    <input type="hidden" name="action" value="edit_record">
                    <input type="hidden" name="record_id" id="edit-record-id">
                    <div class="bg-white px-6 pt-6 pb-6 border-b border-gray-100">
                        <div class="flex items-center justify-between mb-5">
                            <h3 class="text-xl font-bold text-gray-900">Edit Treatment Record</h3>
                            <button type="button" onclick="closeEditRecordModal()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-6 w-6"></i></button>
                        </div>
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Time In</label>
                                    <input type="time" name="time_in" id="edit-time-in" class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Time Out</label>
                                    <input type="time" name="time_out" id="edit-time-out" class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Complaints / Reason</label>
                                <textarea name="complaints" id="edit-complaints" rows="2" class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm"></textarea>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <div class="col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Treatment / Medicines Given</label>
                                    <input type="text" name="treatment" id="edit-treatment" class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                                    <input type="number" name="quantity" id="edit-quantity" min="0" class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-4 flex flex-col sm:flex-row-reverse gap-3">
                        <button type="submit" class="w-full sm:w-auto inline-flex justify-center rounded-xl shadow-sm px-6 py-2.5 bg-pup-maroon text-sm font-bold text-white hover:bg-pup-maroonDark transition-colors">Save Details</button>
                        <button type="button" onclick="closeEditRecordModal()" class="w-full sm:w-auto inline-flex justify-center rounded-xl border border-gray-300 shadow-sm px-6 py-2.5 bg-white text-sm font-bold text-gray-700 hover:bg-gray-50 transition-colors">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteRecordModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div id="deleteRecordOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity opacity-0 duration-300" onclick="closeDeleteRecordModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div id="deleteRecordPanel" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-sm w-full opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95 duration-300">
                <form action="admin_treatment_records.php" method="POST">
                    <input type="hidden" name="action" value="delete_record">
                    <input type="hidden" name="record_id" id="delete-record-id">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10"><i data-lucide="alert-triangle" class="h-6 w-6 text-red-600"></i></div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-bold text-gray-900">Delete Treatment Record</h3>
                                <div class="mt-2"><p class="text-sm text-gray-500">Are you sure you want to permanently delete this treatment record? This action cannot be undone.</p></div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 flex flex-col sm:flex-row-reverse gap-2">
                        <button type="submit" class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-bold text-white hover:bg-red-700 sm:w-auto sm:text-sm transition-colors text-center">Delete</button>
                        <button type="button" onclick="closeDeleteRecordModal()" class="w-full inline-flex justify-center rounded-xl border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-bold text-gray-700 hover:bg-gray-50 sm:w-auto sm:text-sm transition-colors">Cancel</button>
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

        // Data for Dynamic Forms
        const studentsData = <?= json_encode($students_list ?? []) ?>;

        // Dynamic Form Logic for Add Record
        function handleStudentSelect() {
            const select = document.getElementById('add-student-select');
            const studentId = select.value;
            const ageDisplay = document.getElementById('add-student-age');
            const genderDisplay = document.getElementById('add-student-gender');
            
            const student = studentsData.find(s => s.id == studentId);
            
            if (student) {
                genderDisplay.textContent = student.gender || 'Not Specified';
                
                if (student.dob) {
                    const dob = new Date(student.dob);
                    const today = new Date();
                    let age = today.getFullYear() - dob.getFullYear();
                    const m = today.getMonth() - dob.getMonth();
                    if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) {
                        age--;
                    }
                    ageDisplay.textContent = age + ' yrs old';
                } else {
                    ageDisplay.textContent = 'N/A';
                }
            } else {
                ageDisplay.textContent = '--';
                genderDisplay.textContent = '--';
            }
        }

        function handleMedSelect() {
            const select = document.getElementById('add-med-select');
            const qtyInput = document.getElementById('add-med-qty');
            
            if (select.value) {
                qtyInput.disabled = false;
                const option = select.options[select.selectedIndex];
                const maxQty = option.getAttribute('data-qty');
                qtyInput.setAttribute('max', maxQty);
            } else {
                qtyInput.disabled = true;
                qtyInput.value = '';
            }
        }

        // Add Record Modal
        function openAddRecordModal() {
            const m = document.getElementById('addRecordModal');
            m.classList.remove('hidden');
            setTimeout(() => {
                document.getElementById('addRecordOverlay').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('addRecordPanel').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('addRecordPanel').classList.replace('translate-y-4', 'translate-y-0');
                document.getElementById('addRecordPanel').classList.replace('sm:scale-95', 'sm:scale-100');
            }, 10);
        }
        function closeAddRecordModal() {
            document.getElementById('addRecordOverlay').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('addRecordPanel').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('addRecordPanel').classList.replace('translate-y-0', 'translate-y-4');
            document.getElementById('addRecordPanel').classList.replace('sm:scale-100', 'sm:scale-95');
            setTimeout(() => document.getElementById('addRecordModal').classList.add('hidden'), 300);
        }

        // Edit Record Modal
        function openEditRecordModal(btn) {
            const data = JSON.parse(btn.getAttribute('data-record'));
            document.getElementById('edit-record-id').value = data.id;
            document.getElementById('edit-time-in').value = data.time_in || '';
            document.getElementById('edit-time-out').value = data.time_out || '';
            document.getElementById('edit-complaints').value = data.complaints || data.service_reason || '';
            document.getElementById('edit-treatment').value = data.treatment || '';
            document.getElementById('edit-quantity').value = data.quantity || '';

            const m = document.getElementById('editRecordModal');
            m.classList.remove('hidden');
            setTimeout(() => {
                document.getElementById('editRecordOverlay').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('editRecordPanel').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('editRecordPanel').classList.replace('translate-y-4', 'translate-y-0');
                document.getElementById('editRecordPanel').classList.replace('sm:scale-95', 'sm:scale-100');
            }, 10);
        }
        function closeEditRecordModal() {
            document.getElementById('editRecordOverlay').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('editRecordPanel').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('editRecordPanel').classList.replace('translate-y-0', 'translate-y-4');
            document.getElementById('editRecordPanel').classList.replace('sm:scale-100', 'sm:scale-95');
            setTimeout(() => document.getElementById('editRecordModal').classList.add('hidden'), 300);
        }

        // Delete Record Modal
        function openDeleteRecordModal(id) {
            document.getElementById('delete-record-id').value = id;
            const m = document.getElementById('deleteRecordModal');
            m.classList.remove('hidden');
            setTimeout(() => {
                document.getElementById('deleteRecordOverlay').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('deleteRecordPanel').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('deleteRecordPanel').classList.replace('translate-y-4', 'translate-y-0');
                document.getElementById('deleteRecordPanel').classList.replace('sm:scale-95', 'sm:scale-100');
            }, 10);
        }
        function closeDeleteRecordModal() {
            document.getElementById('deleteRecordOverlay').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('deleteRecordPanel').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('deleteRecordPanel').classList.replace('translate-y-0', 'translate-y-4');
            document.getElementById('deleteRecordPanel').classList.replace('sm:scale-100', 'sm:scale-95');
            setTimeout(() => document.getElementById('deleteRecordModal').classList.add('hidden'), 300);
        }

        function openLogoutModal() { const m = document.getElementById('logoutModal'); m.classList.remove('hidden'); setTimeout(() => { document.getElementById('logoutModalOverlay').classList.replace('opacity-0', 'opacity-100'); document.getElementById('logoutModalPanel').classList.replace('opacity-0', 'opacity-100'); document.getElementById('logoutModalPanel').classList.replace('translate-y-4', 'translate-y-0'); document.getElementById('logoutModalPanel').classList.replace('sm:scale-95', 'sm:scale-100'); }, 10); }
        function closeLogoutModal() { document.getElementById('logoutModalOverlay').classList.replace('opacity-100', 'opacity-0'); document.getElementById('logoutModalPanel').classList.replace('opacity-100', 'opacity-0'); document.getElementById('logoutModalPanel').classList.replace('translate-y-0', 'translate-y-4'); document.getElementById('logoutModalPanel').classList.replace('sm:scale-100', 'sm:scale-95'); setTimeout(() => document.getElementById('logoutModal').classList.add('hidden'), 300); }
        
        // Keep this script but remove the 'a' click event listener since global_loader.php handles it.
        // document.addEventListener('DOMContentLoaded', () => { 
        // });
    </script>
</body>
</html>