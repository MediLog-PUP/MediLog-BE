<?php
session_start();
require '../db_connect.php'; 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dentist') {
    header("Location: ../auth/facultylogin.php");
    exit();
}

$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'], $_POST['appointment_id'])) {
    $appt_id = $_POST['appointment_id'];
    $action = $_POST['action'];
    $patient_id = $_POST['patient_id'];
    $dental_proc = $_POST['dental_procedure'] ?? '';
    
    $status = ($action === 'approve') ? 'Approved' : 'Rejected';
    
    $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ?");
    $stmt->execute([$status, $appt_id]);
    
    $proc_text = !empty($dental_proc) ? " (" . $dental_proc . ")" : "";
    $notif = "[Dental] Your request for Dental Services" . $proc_text . " has been " . $status . ".";
    if($status === 'Approved') $notif .= " Please present your online request on your phone upon arrival. All procedures are free.";
    
    $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")->execute([$patient_id, $notif]);
    
    $success_msg = "Request successfully " . strtolower($status) . "!";
}

// Fetch pending dental appointments
$requests = $pdo->query("
    SELECT a.*, u.full_name, u.course, u.section 
    FROM appointments a 
    JOIN users u ON a.patient_id = u.id 
    WHERE a.service_type = 'Dental Services' AND a.status = 'Pending'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dental Requests - MediLog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>tailwind.config = { theme: { extend: { colors: { pup: { maroon: '#880000' } } } } }</script>
</head>
<body class="bg-gray-50 flex h-screen overflow-hidden">
    
    <aside class="hidden md:flex flex-col w-64 bg-gray-900 text-white h-full z-20 flex-shrink-0">
        <div class="p-6 border-b border-gray-800"><span class="font-bold text-xl">MediLog Dental</span></div>
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <a href="dentist_dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 rounded-xl"><i data-lucide="layout-dashboard"></i> Dashboard</a>
            <a href="dental_requests.php" class="flex items-center gap-3 px-4 py-3 bg-blue-600 text-white rounded-xl shadow-sm"><i data-lucide="clipboard-list"></i> Pending Requests</a>
            <a href="dental_procedures.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 rounded-xl"><i data-lucide="stethoscope"></i> Procedures & Eval</a>
        </nav>
        <div class="p-4 border-t border-gray-800">
            <button onclick="openLogoutModal()" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-red-900/50 hover:text-red-400 rounded-xl font-medium transition-colors w-full text-left"><i data-lucide="log-out" class="h-5 w-5"></i> Sign Out</button>
        </div>
    </aside>

    <main class="flex-1 flex flex-col h-full overflow-hidden">
        <header class="bg-white border-b border-gray-200 px-8 py-4 flex items-center justify-between z-10 shadow-sm">
            <h1 class="text-2xl font-bold text-gray-900">Review Dental Requests</h1>
        </header>

        <div class="flex-1 overflow-y-auto p-8">
            <div class="max-w-6xl mx-auto space-y-6">
                
                <?php if($success_msg): ?>
                    <div class="bg-green-50 text-green-700 p-4 rounded-xl text-sm font-bold border border-green-200 flex items-center gap-2"><i data-lucide="check-circle-2" class="h-5 w-5"></i> <?= $success_msg ?></div>
                <?php endif; ?>

                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-200 bg-gray-50 flex gap-3 items-center text-blue-800">
                        <i data-lucide="info" class="h-5 w-5"></i>
                        <p class="text-sm font-medium">Students must secure approval before arriving. Ensure schedules don't overlap.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[800px]">
                            <thead>
                                <tr class="bg-white text-gray-500 text-xs uppercase tracking-wider border-b border-gray-200">
                                    <th class="p-4 font-semibold">Requested Date/Time</th>
                                    <th class="p-4 font-semibold">Student Details</th>
                                    <th class="p-4 font-semibold">Procedure</th>
                                    <th class="p-4 font-semibold">Status</th>
                                    <th class="p-4 font-semibold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-sm">
                                <?php if(count($requests) > 0): ?>
                                    <?php foreach($requests as $r): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="p-4 font-bold text-gray-900"><?= date('M d, Y', strtotime($r['appointment_date'])) ?><br><span class="text-blue-600"><?= date('h:i A', strtotime($r['appointment_time'])) ?></span></td>
                                            <td class="p-4 font-bold text-gray-800"><?= htmlspecialchars($r['full_name']) ?><br><span class="text-xs font-normal text-gray-500"><?= htmlspecialchars($r['course'] . ' ' . $r['section']) ?></span></td>
                                            <td class="p-4 font-bold text-pup-maroon"><?= htmlspecialchars($r['dental_procedure'] ?? 'General Checkup') ?></td>
                                            <td class="p-4"><span class="px-2.5 py-1 bg-yellow-50 text-yellow-700 rounded-full text-xs font-bold border border-yellow-200">Pending</span></td>
                                            <td class="p-4 text-right flex justify-end gap-2">
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="appointment_id" value="<?= $r['id'] ?>">
                                                    <input type="hidden" name="patient_id" value="<?= $r['patient_id'] ?>">
                                                    <input type="hidden" name="dental_procedure" value="<?= htmlspecialchars($r['dental_procedure'] ?? '') ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="bg-green-100 hover:bg-green-200 text-green-700 px-4 py-2 rounded-lg font-bold text-xs flex items-center gap-1 transition-colors"><i data-lucide="check" class="h-4 w-4"></i> Approve</button>
                                                </form>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="appointment_id" value="<?= $r['id'] ?>">
                                                    <input type="hidden" name="patient_id" value="<?= $r['patient_id'] ?>">
                                                    <input type="hidden" name="dental_procedure" value="<?= htmlspecialchars($r['dental_procedure'] ?? '') ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="bg-red-50 hover:bg-red-100 text-red-600 px-4 py-2 rounded-lg font-bold text-xs flex items-center gap-1 transition-colors"><i data-lucide="x" class="h-4 w-4"></i> Reject</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="p-12 text-center text-gray-500">No pending dental requests to review.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
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
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left"><h3 class="text-lg leading-6 font-bold text-gray-900" id="modal-title">Sign Out</h3><div class="mt-2"><p class="text-sm text-gray-500">Are you sure you want to sign out?</p></div></div>
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
        function openLogoutModal() { const m = document.getElementById('logoutModal'); m.classList.remove('hidden'); setTimeout(() => { document.getElementById('logoutModalOverlay').classList.replace('opacity-0', 'opacity-100'); document.getElementById('logoutModalPanel').classList.replace('opacity-0', 'opacity-100'); document.getElementById('logoutModalPanel').classList.replace('translate-y-4', 'translate-y-0'); document.getElementById('logoutModalPanel').classList.replace('sm:scale-95', 'sm:scale-100'); }, 10); }
        function closeLogoutModal() { document.getElementById('logoutModalOverlay').classList.replace('opacity-100', 'opacity-0'); document.getElementById('logoutModalPanel').classList.replace('opacity-100', 'opacity-0'); document.getElementById('logoutModalPanel').classList.replace('translate-y-0', 'translate-y-4'); document.getElementById('logoutModalPanel').classList.replace('sm:scale-100', 'sm:scale-95'); setTimeout(() => document.getElementById('logoutModal').classList.add('hidden'), 300); }
    </script>
</body>
</html>