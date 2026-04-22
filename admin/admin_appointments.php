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

// Automatically add necessary columns to health_records if they don't exist
$new_columns = [
    'status' => "VARCHAR(50) DEFAULT 'Completed'",
    'time_in' => 'TIME',
    'time_out' => 'TIME',
    'complaints' => 'TEXT',
    'treatment' => 'VARCHAR(255)',
    'quantity' => 'INT',
    'medicine_id' => 'INT'
];
foreach ($new_columns as $col => $type) {
    try {
        $pdo->exec("ALTER TABLE health_records ADD COLUMN $col $type");
    } catch (PDOException $e) {
        // Ignore error if column already exists
    }
}

// Handle Appointment Updates (Start / Cancel / Evaluate General / Delete)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['appointment_id'], $_POST['action'])) {
    $appt_id = intval($_POST['appointment_id']);
    $action = $_POST['action'];

    // Fetch Admin Data
    $adminStmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $adminStmt->execute([$user_id]);
    $admin_name = $adminStmt->fetchColumn();
    
    if ($action === 'start') {
        $stmt = $pdo->prepare("UPDATE appointments SET status = 'Completed' WHERE id = ?");
        $stmt->execute([$appt_id]);
        
        $apptStmt = $pdo->prepare("SELECT a.patient_id, a.service_type, u.full_name as patient_name FROM appointments a JOIN users u ON a.patient_id = u.id WHERE a.id = ?");
        $apptStmt->execute([$appt_id]);
        $apptData = $apptStmt->fetch();
        
        if ($apptData) {
            // Insert basic health record for standard complete
            $hrStmt = $pdo->prepare("INSERT INTO health_records (patient_id, physician_id, visit_date, service_reason, diagnosis, status) VALUES (?, ?, CURDATE(), ?, 'Consultation completed. See physical records for details.', 'Completed')");
            $hrStmt->execute([$apptData['patient_id'], $user_id, $apptData['service_type']]);
            
            // Notify Student
            $notif_msg = "[Appointment] Faculty " . $admin_name . " completed your appointment for " . $apptData['service_type'] . ". A new health record has been added.";
            $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")->execute([$apptData['patient_id'], $notif_msg]);

            // Notify Admins
            $admin_notif_msg = "[Appointment] Faculty " . $admin_name . " completed an appointment for " . $apptData['patient_name'] . ".";
            $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (NULL, ?)")->execute([$admin_notif_msg]);
        }
        
        $success_msg = "Session completed successfully and health record created.";

    } elseif ($action === 'evaluate_general') {
        $time_in = !empty($_POST['time_in']) ? $_POST['time_in'] : null;
        $time_out = !empty($_POST['time_out']) ? $_POST['time_out'] : null;
        $complaints = $_POST['complaints'] ?? '';
        $medicine_id = !empty($_POST['medicine_id']) ? intval($_POST['medicine_id']) : null;
        $quantity = !empty($_POST['quantity']) ? intval($_POST['quantity']) : null;
        $other_treatment = $_POST['other_treatment'] ?? '';

        $treatment_text = $other_treatment;
        $error_eval = false;

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
                $notif_msg = "[Inventory] Faculty " . $admin_name . " dispensed " . $quantity . " pcs of " . $med['name'] . " for an appointment.";
                $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (NULL, ?)")->execute([$notif_msg]);

                // Check for CRITICAL low stock (<= 5)
                if ($new_qty <= 5) {
                    $critical_notif = "[Inventory] CRITICAL: " . $med['name'] . " stock is very low! Only " . $new_qty . " left.";
                    $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (NULL, ?)")->execute([$critical_notif]);
                }

            } else {
                $error_msg = "Insufficient stock for the selected medicine.";
                $error_eval = true;
            }
        }

        if (!$error_eval) {
            // Fetch Appt Data
            $apptStmt = $pdo->prepare("SELECT a.patient_id, a.service_type, a.appointment_date, u.full_name as patient_name FROM appointments a JOIN users u ON a.patient_id = u.id WHERE a.id = ?");
            $apptStmt->execute([$appt_id]);
            $apptData = $apptStmt->fetch();

            if ($apptData) {
                // Insert comprehensive health record
                $hrStmt = $pdo->prepare("INSERT INTO health_records (patient_id, physician_id, visit_date, time_in, time_out, service_reason, complaints, treatment, quantity, medicine_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Completed')");
                $hrStmt->execute([$apptData['patient_id'], $user_id, $apptData['appointment_date'], $time_in, $time_out, $apptData['service_type'], $complaints, $treatment_text, $quantity, $medicine_id]);
                
                // Mark appointment as Completed
                $pdo->prepare("UPDATE appointments SET status = 'Completed' WHERE id = ?")->execute([$appt_id]);

                // Notify Student
                $notif_msg = "[Appointment] Faculty " . $admin_name . " evaluated your General Consultation and added a detailed health record.";
                $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")->execute([$apptData['patient_id'], $notif_msg]);

                // Notify Admins
                $admin_notif_msg = "[Appointment] Faculty " . $admin_name . " evaluated a General Consultation for " . $apptData['patient_name'] . ".";
                $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (NULL, ?)")->execute([$admin_notif_msg]);
                
                $success_msg = "General Consultation evaluated, health record created, and appointment completed.";
            }
        }

    } elseif ($action === 'cancel') {
        $stmt = $pdo->prepare("UPDATE appointments SET status = 'Cancelled' WHERE id = ?");
        $stmt->execute([$appt_id]);
        
        $apptStmt = $pdo->prepare("SELECT a.patient_id, a.service_type, u.full_name as patient_name FROM appointments a JOIN users u ON a.patient_id = u.id WHERE a.id = ?");
        $apptStmt->execute([$appt_id]);
        $apptData = $apptStmt->fetch();
        
        if ($apptData) {
            // Notify Student
            $notif_msg = "[Appointment] Faculty " . $admin_name . " cancelled your appointment for " . $apptData['service_type'] . ".";
            $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")->execute([$apptData['patient_id'], $notif_msg]);

            // Notify Admins
            $admin_notif_msg = "[Appointment] Faculty " . $admin_name . " cancelled an appointment for " . $apptData['patient_name'] . ".";
            $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (NULL, ?)")->execute([$admin_notif_msg]);
        }
        
        $success_msg = "Appointment cancelled.";
        
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM appointments WHERE id = ?");
        if ($stmt->execute([$appt_id])) {
            $success_msg = "Appointment record permanently deleted.";
        } else {
            $error_msg = "Failed to delete the appointment.";
        }
    }
}

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

// Fetch Medicines for Evaluate Dropdown
$medsListStmt = $pdo->query("SELECT id, name, quantity FROM medicine_inventory WHERE quantity > 0 ORDER BY name ASC");
$meds_list = $medsListStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Appointments organized by Status
try {
    $pendingStmt = $pdo->query("SELECT a.*, u.full_name as patient_name, u.course, u.section FROM appointments a JOIN users u ON a.patient_id = u.id WHERE a.status = 'Pending' ORDER BY appointment_date ASC, appointment_time ASC");
    $pending = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

    $completedStmt = $pdo->query("SELECT a.*, u.full_name as patient_name, u.course, u.section FROM appointments a JOIN users u ON a.patient_id = u.id WHERE a.status = 'Completed' ORDER BY appointment_date DESC, appointment_time DESC");
    $completed = $completedStmt->fetchAll(PDO::FETCH_ASSOC);

    $cancelledStmt = $pdo->query("SELECT a.*, u.full_name as patient_name, u.course, u.section FROM appointments a JOIN users u ON a.patient_id = u.id WHERE a.status = 'Cancelled' ORDER BY appointment_date DESC, appointment_time DESC");
    $cancelled = $cancelledStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error fetching appointments: " . $e->getMessage());
}

function renderAppointmentCard($appt, $type) {
    $statusClass = $appt['status'] == 'Pending' ? 'bg-yellow-100 text-yellow-800' : ($appt['status'] == 'Completed' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800');
    ?>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 hover:shadow-md transition-shadow">
        <div class="flex justify-between items-start mb-4">
            <div class="bg-blue-100 text-blue-700 p-2.5 rounded-xl"><i data-lucide="calendar-clock" class="h-5 w-5"></i></div>
            <span class="text-xs font-bold px-2 py-1 rounded <?= $statusClass ?>">
                <?= htmlspecialchars($appt['status']) ?>
            </span>
        </div>
        <h3 class="font-bold text-gray-900 text-lg"><?= htmlspecialchars($appt['patient_name']) ?></h3>
        <p class="text-xs text-gray-500 mb-4"><?= htmlspecialchars($appt['course'] . ' | Sec: ' . $appt['section']) ?></p>
        
        <div class="space-y-2 text-sm text-gray-600 mb-4">
            <p class="flex items-center gap-2"><i data-lucide="stethoscope" class="h-4 w-4"></i> <?= htmlspecialchars($appt['service_type']) ?></p>
            <p class="flex items-center gap-2"><i data-lucide="calendar" class="h-4 w-4"></i> <?= date("M d, Y", strtotime($appt['appointment_date'])) ?></p>
            <p class="flex items-center gap-2"><i class="h-4 w-4" data-lucide="clock"></i> <?= date("h:i A", strtotime($appt['appointment_time'])) ?></p>
        </div>
        
        <?php if($type == 'pending'): ?>
            <div class="flex gap-2 mt-4 pt-4 border-t border-gray-100">
                <?php if ($appt['service_type'] === 'General Consultation'): ?>
                    <button type="button" onclick="openEvaluateModal(this)" data-appt='<?= htmlspecialchars(json_encode($appt), ENT_QUOTES, 'UTF-8') ?>' class="flex-1 bg-blue-50 text-blue-700 hover:bg-blue-100 py-2 rounded-xl text-xs font-bold transition-colors">Evaluate</button>
                <?php else: ?>
                    <form method="POST" action="admin_appointments.php" class="flex-1">
                        <input type="hidden" name="appointment_id" value="<?= $appt['id'] ?>">
                        <button type="submit" name="action" value="start" class="w-full bg-green-50 text-green-700 hover:bg-green-100 py-2 rounded-xl text-xs font-bold transition-colors">Complete</button>
                    </form>
                <?php endif; ?>

                <form method="POST" action="admin_appointments.php" class="flex-1">
                    <input type="hidden" name="appointment_id" value="<?= $appt['id'] ?>">
                    <button type="submit" name="action" value="cancel" class="w-full bg-red-50 text-red-700 hover:bg-red-100 py-2 rounded-xl text-xs font-bold transition-colors">Cancel</button>
                </form>
            </div>
        <?php else: ?>
            <div class="flex gap-2 mt-4 pt-4 border-t border-gray-100">
                <form method="POST" action="admin_appointments.php" class="w-full" onsubmit="return confirm('Are you sure you want to permanently delete this appointment record?');">
                    <input type="hidden" name="appointment_id" value="<?= $appt['id'] ?>">
                    <button type="submit" name="action" value="delete" class="w-full bg-red-50 text-red-700 hover:bg-red-100 py-2 rounded-xl text-xs font-bold transition-colors flex items-center justify-center gap-1">
                        <i data-lucide="trash-2" class="h-4 w-4"></i> Delete Record
                    </button>
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
    <title>Appointments - MediLog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] }, colors: { pup: { maroon: '#880000', gold: '#F1B500' } } } } }</script>
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
            <a href="admin_appointments.php" class="flex items-center gap-3 px-4 py-3 bg-pup-maroon text-white rounded-xl font-medium transition-colors shadow-sm"><i data-lucide="calendar" class="h-5 w-5"></i> Appointments</a>
            <a href="admin_clearance.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="file-check-2" class="h-5 w-5"></i> Clearances</a>
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
            <a href="patient_records.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="users" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Patients</span></a>
            <a href="admin_appointments.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-pup-maroon transition-colors"><i data-lucide="calendar" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Appts</span></a>
            <a href="admin_inquiries.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors relative"><i data-lucide="message-square" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Chat</span><span class="absolute top-0 w-8 h-1 bg-pup-maroon rounded-b-md"></span></a>
            <a href="admin_profile.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="user-cog" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Profile</span></a>
            <a href="super_admin_users.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="shield-alert" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Admins</span></a>
        </div>
    </nav>

    <main class="flex-1 flex flex-col h-full overflow-hidden">
        <header class="bg-white border-b border-gray-200 px-4 sm:px-8 py-4 flex items-center justify-between shadow-sm z-10">
            <div class="flex items-center gap-3">
                <div class="md:hidden bg-pup-maroon text-white p-1.5 rounded-lg mr-2"><i data-lucide="shield-plus" class="h-5 w-5 text-pup-gold"></i></div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900">Appointments Schedule</h1>
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
                <?php if($error_msg): ?>
                    <div class="bg-red-50 text-red-700 p-4 rounded-xl text-sm font-medium border border-red-200 flex items-center gap-2 mb-6">
                        <i data-lucide="alert-circle" class="h-5 w-5"></i> <?= htmlspecialchars($error_msg) ?>
                    </div>
                <?php endif; ?>

                <!-- TABS NAVIGATION -->
                <div class="border-b border-gray-200 mb-6">
                    <nav class="-mb-px flex gap-6" aria-label="Tabs">
                        <button onclick="switchTab('pending')" id="tab-pending" class="tab-btn border-pup-maroon text-pup-maroon whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2 transition-colors">
                            Pending
                            <span class="bg-yellow-100 text-yellow-800 py-0.5 px-2 rounded-full text-xs font-bold"><?= count($pending) ?></span>
                        </button>
                        <button onclick="switchTab('completed')" id="tab-completed" class="tab-btn border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2 transition-colors">
                            Completed
                            <span class="bg-gray-100 text-gray-600 py-0.5 px-2 rounded-full text-xs font-bold"><?= count($completed) ?></span>
                        </button>
                        <button onclick="switchTab('cancelled')" id="tab-cancelled" class="tab-btn border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2 transition-colors">
                            Cancelled
                            <span class="bg-gray-100 text-gray-600 py-0.5 px-2 rounded-full text-xs font-bold"><?= count($cancelled) ?></span>
                        </button>
                    </nav>
                </div>

                <!-- TABS CONTENT -->
                
                <!-- Pending Tab -->
                <div id="content-pending" class="tab-content grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if(count($pending) > 0): ?>
                        <?php foreach($pending as $appt): ?>
                            <?php renderAppointmentCard($appt, 'pending'); ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-span-full p-8 text-center text-gray-500 bg-white rounded-2xl border border-gray-100">No pending appointments.</div>
                    <?php endif; ?>
                </div>

                <!-- Completed Tab -->
                <div id="content-completed" class="tab-content hidden grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if(count($completed) > 0): ?>
                        <?php foreach($completed as $appt): ?>
                            <?php renderAppointmentCard($appt, 'completed'); ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-span-full p-8 text-center text-gray-500 bg-white rounded-2xl border border-gray-100">No completed appointments.</div>
                    <?php endif; ?>
                </div>

                <!-- Cancelled Tab -->
                <div id="content-cancelled" class="tab-content hidden grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if(count($cancelled) > 0): ?>
                        <?php foreach($cancelled as $appt): ?>
                            <?php renderAppointmentCard($appt, 'cancelled'); ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-span-full p-8 text-center text-gray-500 bg-white rounded-2xl border border-gray-100">No cancelled appointments.</div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>

    <!-- Evaluate General Consultation Modal -->
    <div id="evaluateModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div id="evaluateModalOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity opacity-0 duration-300" onclick="closeEvaluateModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div id="evaluateModalPanel" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95 duration-300">
                <form action="admin_appointments.php" method="POST" class="flex flex-col max-h-[90vh]">
                    <input type="hidden" name="action" value="evaluate_general">
                    <input type="hidden" name="appointment_id" id="eval-appt-id">
                    
                    <div class="bg-white px-6 pt-6 pb-4 border-b border-gray-100 flex-shrink-0">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xl font-bold text-gray-900">Evaluate General Consultation</h3>
                            <button type="button" onclick="closeEvaluateModal()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-6 w-6"></i></button>
                        </div>
                        <p class="text-sm text-gray-500 mt-2">Patient: <span id="eval-patient-name" class="font-bold text-gray-900"></span></p>
                    </div>
                    
                    <div class="px-6 py-6 overflow-y-auto space-y-4 bg-white flex-1">
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">Complaints / Reason <span class="text-red-500">*</span></label>
                            <textarea name="complaints" rows="2" required placeholder="Describe the student's symptoms..." class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm"></textarea>
                        </div>

                        <div class="border-t border-gray-100 pt-4">
                            <h4 class="text-sm font-bold text-gray-900 mb-3">Treatment Details</h4>
                            <div class="grid grid-cols-3 gap-4 mb-4">
                                <div class="col-span-2">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Select Inventory Medicine (Optional)</label>
                                    <select name="medicine_id" id="eval-med-select" class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm bg-white" onchange="handleEvalMedSelect()">
                                        <option value="">No medicine dispensed</option>
                                        <?php foreach($meds_list as $m): 
                                            if($m['quantity'] > 0):
                                        ?>
                                            <option value="<?= $m['id'] ?>" data-qty="<?= $m['quantity'] ?>"><?= htmlspecialchars($m['name']) ?> (Stock: <?= $m['quantity'] ?>)</option>
                                        <?php endif; endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Dispense Qty</label>
                                    <input type="number" name="quantity" id="eval-med-qty" min="1" disabled placeholder="0" class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm disabled:bg-gray-100 disabled:cursor-not-allowed">
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
                        <button type="submit" class="w-full sm:w-auto inline-flex justify-center rounded-xl shadow-sm px-6 py-2.5 bg-pup-maroon text-sm font-bold text-white hover:bg-pup-maroonDark transition-colors">Complete Appointment</button>
                        <button type="button" onclick="closeEvaluateModal()" class="w-full sm:w-auto inline-flex justify-center rounded-xl border border-gray-300 shadow-sm px-6 py-2.5 bg-white text-sm font-bold text-gray-700 hover:bg-gray-50 transition-colors">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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
            localStorage.setItem('activeApptTab', tabId);
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
            const savedTab = localStorage.getItem('activeApptTab') || 'pending';
            switchTab(savedTab);
        });

        // Evaluate Modal Logic
        function handleEvalMedSelect() {
            const select = document.getElementById('eval-med-select');
            const qtyInput = document.getElementById('eval-med-qty');
            
            if (select.value) {
                qtyInput.disabled = false;
                const option = select.options[select.selectedIndex];
                const maxQty = option.getAttribute('data-qty');
                qtyInput.setAttribute('max', maxQty);
                if (!qtyInput.value) qtyInput.value = 1;
            } else {
                qtyInput.disabled = true;
                qtyInput.value = '';
            }
        }

        function openEvaluateModal(btn) {
            const data = JSON.parse(btn.getAttribute('data-appt'));
            document.getElementById('eval-appt-id').value = data.id;
            document.getElementById('eval-patient-name').innerText = data.patient_name;
            
            const m = document.getElementById('evaluateModal');
            m.classList.remove('hidden');
            setTimeout(() => {
                document.getElementById('evaluateModalOverlay').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('evaluateModalPanel').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('evaluateModalPanel').classList.replace('translate-y-4', 'translate-y-0');
                document.getElementById('evaluateModalPanel').classList.replace('sm:scale-95', 'sm:scale-100');
            }, 10);
        }

        function closeEvaluateModal() {
            document.getElementById('evaluateModalOverlay').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('evaluateModalPanel').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('evaluateModalPanel').classList.replace('translate-y-0', 'translate-y-4');
            document.getElementById('evaluateModalPanel').classList.replace('sm:scale-100', 'sm:scale-95');
            setTimeout(() => document.getElementById('evaluateModal').classList.add('hidden'), 300);
        }
    </script>
</body>
</html>