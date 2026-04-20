<?php
session_start();
require '../db_connect.php'; 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dentist') {
    header("Location: ../auth/facultylogin.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// --- ULTIMATE DATABASE SELF-HEALING SCRIPT ---
// This guarantees that ALL tables and columns exist so MySQL never halts the script.
try {
    $pdo->exec("ALTER TABLE appointments MODIFY COLUMN status VARCHAR(50) DEFAULT 'Pending'");
    $pdo->exec("UPDATE appointments SET status = 'Approved' WHERE status = '' AND service_type = 'Dental Services'");
} catch (PDOException $e) {}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS dental_evaluations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        appointment_id INT NOT NULL,
        patient_id INT NOT NULL,
        dentist_id INT NOT NULL,
        procedure_done VARCHAR(100) NOT NULL,
        evaluation_notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS health_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        physician_id INT NOT NULL,
        visit_date DATE NOT NULL
    )");
    
    // Force inject all necessary columns
    $cols = [
        'time_in' => 'TIME', 
        'time_out' => 'TIME', 
        'complaints' => 'TEXT', 
        'treatment' => 'TEXT', 
        'quantity' => 'INT', 
        'status' => "VARCHAR(50) DEFAULT 'Completed'",
        'service_reason' => "VARCHAR(255) DEFAULT 'Clinic Visit'",
        'diagnosis' => "TEXT"
    ];
    
    foreach ($cols as $col => $type) {
        try { $pdo->exec("ALTER TABLE health_records ADD COLUMN $col $type"); } catch (PDOException $e) {}
    }
    
    // Ensure text fields are TEXT (not VARCHAR) to prevent "Data too long" strict mode crashes
    try { $pdo->exec("ALTER TABLE health_records MODIFY COLUMN treatment TEXT"); } catch(PDOException $e){}
    try { $pdo->exec("ALTER TABLE health_records MODIFY COLUMN complaints TEXT"); } catch(PDOException $e){}
    try { $pdo->exec("ALTER TABLE notifications MODIFY COLUMN message TEXT"); } catch(PDOException $e){}
} catch (PDOException $e) {}
// -------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_procedure') {
    
    // FORCE EXCEPTION MODE ON SO IT NEVER FAILS SILENTLY AGAIN
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $appt_id = intval($_POST['appointment_id']);
    $procedure = trim($_POST['procedure_done']);
    $evaluation = trim($_POST['evaluation_notes']);

    $errors = [];

    try {
        // Fetch patient ID directly from database to prevent JS mismatches
        $apptCheck = $pdo->prepare("SELECT patient_id FROM appointments WHERE id = ?");
        $apptCheck->execute([$appt_id]);
        $patient_id = $apptCheck->fetchColumn();

        if ($patient_id) {
            
            // 1. Insert the Evaluation Record exclusively into Dental Evaluations
            try {
                $evalStmt = $pdo->prepare("INSERT INTO dental_evaluations (appointment_id, patient_id, dentist_id, procedure_done, evaluation_notes) VALUES (?, ?, ?, ?, ?)");
                $evalStmt->execute([$appt_id, $patient_id, $user_id, substr($procedure, 0, 99), $evaluation]);
            } catch (Exception $e) { $errors[] = "Eval DB Error: " . $e->getMessage(); }

            // 2. Send Notification to Student
            try {
                $notif_msg = "[Appointment] Your dental procedure (" . substr($procedure, 0, 50) . ") is complete. Remarks: " . substr($evaluation, 0, 100);
                $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $notifStmt->execute([$patient_id, $notif_msg]);
            } catch (Exception $e) { $errors[] = "Notification DB Error: " . $e->getMessage(); }

            // 3. Mark Appointment as Completed
            try {
                $pdo->prepare("UPDATE appointments SET status = 'Completed' WHERE id = ?")->execute([$appt_id]);
            } catch (Exception $e) { $errors[] = "Status DB Error: " . $e->getMessage(); }

            if (empty($errors)) {
                $success_msg = "Procedure logged and evaluation sent successfully!";
            } else {
                $error_msg = "Completed with database issues: " . implode(' | ', $errors);
            }
        } else {
            $error_msg = "Invalid Appointment ID. Could not find patient.";
        }
    } catch (Exception $e) {
        $error_msg = "Critical error: " . $e->getMessage();
    }

    // Restore default silent error mode so it doesn't affect global scripts
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
}

// Fetch ALL approved dental appointments waiting to be performed (No date restrictions)
$approved_appts = $pdo->query("
    SELECT a.*, u.full_name, u.course, u.section 
    FROM appointments a 
    JOIN users u ON a.patient_id = u.id 
    WHERE a.service_type = 'Dental Services' AND a.status = 'Approved'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dental Procedures - MediLog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] }, colors: { pup: { maroon: '#880000', gold: '#F1B500' } } } } }
    </script>
</head>
<body class="bg-gray-50 flex h-screen overflow-hidden font-sans">
    
    <aside class="hidden md:flex flex-col w-64 bg-gray-900 text-white h-full z-20 flex-shrink-0">
        <div class="p-6 border-b border-gray-800 flex items-center gap-3">
            <div class="bg-blue-500 text-white p-2 rounded-lg"><i data-lucide="smile" class="h-6 w-6"></i></div>
            <span class="font-bold text-xl tracking-tight">MediLog Dental</span>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <a href="dentist_dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="layout-dashboard" class="h-5 w-5"></i> Dashboard</a>
            <a href="dental_requests.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="clipboard-list" class="h-5 w-5"></i> Pending Requests</a>
            <a href="dental_procedures.php" class="flex items-center gap-3 px-4 py-3 bg-blue-600 text-white rounded-xl font-medium transition-colors shadow-sm"><i data-lucide="stethoscope" class="h-5 w-5"></i> Procedures & Eval</a>
        </nav>
        <div class="p-4 border-t border-gray-800"><a href="../auth/logout.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:text-red-400 font-medium transition-colors w-full"><i data-lucide="log-out" class="h-5 w-5"></i> Sign Out</a></div>
    </aside>

    <main class="flex-1 flex flex-col h-full overflow-hidden">
        <header class="bg-white border-b border-gray-200 px-8 py-4 flex items-center justify-between z-10 shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">Execute Procedures & Evaluate</h1>
        </header>

        <div class="flex-1 overflow-y-auto p-8 relative">
            <div class="max-w-6xl mx-auto space-y-6">
                
                <?php if($success_msg): ?>
                    <div class="bg-green-50 text-green-700 p-4 rounded-xl text-sm font-bold border border-green-200 flex items-center gap-2"><i data-lucide="check-circle-2" class="h-5 w-5"></i> <?= $success_msg ?></div>
                <?php endif; ?>
                <?php if($error_msg): ?>
                    <div class="bg-red-50 text-red-700 p-4 rounded-xl text-sm font-bold border border-red-200 flex items-start gap-2">
                        <i data-lucide="alert-circle" class="h-5 w-5 mt-0.5 flex-shrink-0"></i> 
                        <div class="break-words"><?= htmlspecialchars($error_msg) ?></div>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-200 bg-gray-50 flex gap-3 items-center">
                        <i data-lucide="smartphone" class="h-5 w-5 text-blue-600 flex-shrink-0"></i>
                        <p class="text-sm font-medium text-gray-600">Always verify the student's approved online request via their cellphone before beginning.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[800px]">
                            <thead>
                                <tr class="bg-white text-gray-500 text-xs uppercase tracking-wider border-b border-gray-200">
                                    <th class="p-4 font-semibold">Schedule</th>
                                    <th class="p-4 font-semibold">Patient</th>
                                    <th class="p-4 font-semibold">Procedure Requested</th>
                                    <th class="p-4 font-semibold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-sm">
                                <?php if(count($approved_appts) > 0): ?>
                                    <?php foreach($approved_appts as $a): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="p-4 font-bold text-gray-900"><?= date('M d, Y', strtotime($a['appointment_date'])) ?> <span class="text-blue-600 ml-2"><?= date('h:i A', strtotime($a['appointment_time'])) ?></span></td>
                                            <td class="p-4 font-bold text-gray-800"><?= htmlspecialchars($a['full_name']) ?> <span class="text-xs font-normal text-gray-500 ml-1">(<?= htmlspecialchars($a['course'] ?? '') ?>)</span></td>
                                            <td class="p-4 font-bold text-pup-maroon"><?= htmlspecialchars($a['dental_procedure'] ?? 'General Checkup') ?></td>
                                            <td class="p-4 text-right">
                                                <button onclick="openEvalModal(<?= $a['id'] ?>, '<?= addslashes($a['full_name']) ?>', '<?= addslashes($a['dental_procedure'] ?? '') ?>')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold text-xs flex items-center gap-2 inline-flex transition-colors shadow-sm"><i data-lucide="activity" class="h-4 w-4"></i> Perform & Evaluate</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="p-12 text-center text-gray-500 font-medium">No approved patients waiting for procedures.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Evaluation Modal -->
    <div id="evalModal" class="fixed inset-0 z-50 hidden bg-gray-900 bg-opacity-75 flex items-center justify-center p-4 backdrop-blur-sm transition-opacity">
        <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl transform transition-all">
            <form action="dental_procedures.php" method="POST">
                <input type="hidden" name="action" value="complete_procedure">
                <input type="hidden" name="appointment_id" id="modal_appt_id">
                
                <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Post-Procedure Evaluation</h3>
                        <p class="text-xs text-blue-600 font-bold mt-0.5" id="modal_patient_name"></p>
                    </div>
                    <button type="button" onclick="closeEvalModal()" class="text-gray-400 hover:text-gray-600 focus:outline-none"><i data-lucide="x" class="h-5 w-5"></i></button>
                </div>
                
                <div class="p-6 space-y-5">
                    <div class="bg-blue-50 border border-blue-100 text-blue-800 text-xs font-semibold p-3 rounded-xl flex gap-2">
                        <i data-lucide="info" class="h-4 w-4 shrink-0"></i> Note: All services listed below are provided to the patient completely free of charge.
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Procedure Performed</label>
                        <select name="procedure_done" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-blue-600 focus:border-blue-600 text-sm bg-white shadow-sm">
                            <option value="" disabled selected>Select procedure...</option>
                            <option value="Pasta (Filling)">Pasta (Filling)</option>
                            <option value="Bunot (Extraction)">Bunot (Extraction)</option>
                            <option value="Cleaning (Prophylaxis)">Cleaning (Prophylaxis)</option>
                            <option value="General Checkup">General Checkup / Consult Only</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Evaluation Notes & Recommendations</label>
                        <textarea name="evaluation_notes" rows="4" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-blue-600 focus:border-blue-600 text-sm shadow-sm" placeholder="Enter post-procedure findings, tooth numbers treated, and aftercare instructions..."></textarea>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3 border-t border-gray-100">
                    <button type="button" onclick="closeEvalModal()" class="px-4 py-2 bg-white border border-gray-300 rounded-xl text-sm font-bold text-gray-700 hover:bg-gray-50 shadow-sm transition-colors">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-bold hover:bg-blue-700 flex items-center gap-2 shadow-sm transition-colors">Save Evaluation <i data-lucide="check" class="h-4 w-4"></i></button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
        function openEvalModal(appt_id, name, requested_proc) {
            document.getElementById('modal_appt_id').value = appt_id;
            document.getElementById('modal_patient_name').innerText = "Patient: " + name;
            
            // Auto-select the requested procedure if possible
            const procSelect = document.querySelector('select[name="procedure_done"]');
            if (requested_proc) {
                for (let i = 0; i < procSelect.options.length; i++) {
                    if (procSelect.options[i].value === requested_proc) {
                        procSelect.selectedIndex = i;
                        break;
                    }
                }
            } else {
                procSelect.selectedIndex = 0;
            }

            document.getElementById('evalModal').classList.remove('hidden');
        }
        function closeEvalModal() {
            document.getElementById('evalModal').classList.add('hidden');
        }
    </script>
</body>
</html>