<?php
session_start();
require '../db_connect.php'; 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dentist') {
    header("Location: ../auth/facultylogin.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Auto-add course and section to prevent SQL crashes if they don't exist
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN course VARCHAR(100) DEFAULT NULL, ADD COLUMN section VARCHAR(50) DEFAULT NULL");
} catch (PDOException $e) {}

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

// Fetch basic dental statistics
$pending_requests = $pdo->query("SELECT COUNT(*) FROM appointments WHERE service_type = 'Dental Services' AND status = 'Pending'")->fetchColumn();
// Removed the CURDATE() restriction so approved requests don't vanish due to timezone issues
$upcoming_approved = $pdo->query("SELECT COUNT(*) FROM appointments WHERE service_type = 'Dental Services' AND status = 'Approved'")->fetchColumn();

$total_evaluations = $pdo->prepare("SELECT COUNT(*) FROM dental_evaluations WHERE dentist_id = ?");
$total_evaluations->execute([$user_id]);
$eval_count = $total_evaluations->fetchColumn();

// Fetch ALL approved scheduled patients (No date restrictions, stays until completed)
$scheduledStmt = $pdo->query("
    SELECT a.*, u.full_name, u.course, u.section 
    FROM appointments a 
    JOIN users u ON a.patient_id = u.id 
    WHERE a.service_type = 'Dental Services' AND a.status = 'Approved'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$upcoming_patients = $scheduledStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dental Dashboard - MediLog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] }, colors: { pup: { maroon: '#880000', gold: '#F1B500' } } } } }
    </script>
    <style> .no-scrollbar::-webkit-scrollbar { display: none; } .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; } </style>
</head>
<body class="font-sans antialiased text-gray-800 bg-gray-50 flex h-screen overflow-hidden">

    <?php include '../global_loader.php'; ?>

    <aside class="hidden md:flex flex-col w-64 bg-gray-900 text-white h-full shadow-xl z-20 flex-shrink-0">
        <div class="p-6 flex items-center gap-3 border-b border-gray-800">
            <div class="bg-blue-500 text-white p-2 rounded-lg"><i data-lucide="smile" class="h-6 w-6"></i></div>
            <span class="font-bold text-xl tracking-tight text-white">MediLog Dental</span>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <a href="dentist_dashboard.php" class="flex items-center gap-3 px-4 py-3 bg-blue-600 text-white rounded-xl font-medium transition-colors shadow-sm"><i data-lucide="layout-dashboard" class="h-5 w-5"></i> Dashboard</a>
            <a href="dental_requests.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="clipboard-list" class="h-5 w-5"></i> Pending Requests</a>
            <a href="dental_procedures.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="stethoscope" class="h-5 w-5"></i> Procedures & Eval</a>
        </nav>
        <div class="p-4 border-t border-gray-800">
            <a href="../logout.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-red-900/50 hover:text-red-400 rounded-xl font-medium transition-colors w-full"><i data-lucide="log-out" class="h-5 w-5"></i> Sign Out</a>
        </div>
    </aside>

    <main class="flex-1 flex flex-col h-full overflow-hidden relative">
        <header class="bg-white border-b border-gray-200 px-4 md:px-8 py-4 flex items-center justify-between z-10 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="md:hidden bg-blue-500 text-white p-1.5 rounded-lg mr-2"><i data-lucide="smile" class="h-5 w-5"></i></div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900">Dental Department</h1>
            </div>
            <div class="flex items-center gap-3 pl-4 border-l border-gray-200">
                <div class="text-right">
                    <p class="text-sm font-semibold text-gray-900">Dr. <?= htmlspecialchars($user['full_name']) ?></p>
                    <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">Dental Faculty</span>
                </div>
                <img src="<?= $profile_pic ?>" alt="Profile" class="h-10 w-10 rounded-full border-2 border-gray-200 object-cover">
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 sm:p-8">
            <div class="max-w-6xl mx-auto space-y-6">
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm flex items-center gap-4 border-l-4 border-l-yellow-500">
                        <div class="bg-yellow-50 p-4 rounded-xl text-yellow-600"><i data-lucide="clock" class="h-8 w-8"></i></div>
                        <div><p class="text-sm font-medium text-gray-500">Pending Requests</p><p class="text-2xl font-bold text-gray-900"><?= $pending_requests ?></p></div>
                    </div>
                    <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm flex items-center gap-4 border-l-4 border-l-blue-500">
                        <div class="bg-blue-50 p-4 rounded-xl text-blue-600"><i data-lucide="calendar-check" class="h-8 w-8"></i></div>
                        <div><p class="text-sm font-medium text-gray-500">Approved Patients</p><p class="text-2xl font-bold text-gray-900"><?= $upcoming_approved ?></p></div>
                    </div>
                    <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm flex items-center gap-4 border-l-4 border-l-green-500">
                        <div class="bg-green-50 p-4 rounded-xl text-green-600"><i data-lucide="file-check-2" class="h-8 w-8"></i></div>
                        <div><p class="text-sm font-medium text-gray-500">My Evaluations</p><p class="text-2xl font-bold text-gray-900"><?= $eval_count ?></p></div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mt-6">
                    <div class="p-6 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                        <h2 class="text-lg font-bold text-gray-900">Approved Patient Queue</h2>
                        <a href="dental_procedures.php" class="text-sm font-semibold text-blue-600 hover:underline flex items-center gap-1">Go to Procedures <i data-lucide="arrow-right" class="h-4 w-4"></i></a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[800px]">
                            <thead>
                                <tr class="bg-white text-gray-500 text-xs uppercase tracking-wider border-b border-gray-200">
                                    <th class="p-4 font-semibold">Date & Time</th>
                                    <th class="p-4 font-semibold">Patient Name</th>
                                    <th class="p-4 font-semibold">Procedure</th>
                                    <th class="p-4 font-semibold">Verification Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-sm">
                                <?php if(count($upcoming_patients) > 0): ?>
                                    <?php foreach($upcoming_patients as $pt): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="p-4 font-bold text-gray-900"><?= date('M d, Y', strtotime($pt['appointment_date'])) ?> <span class="text-blue-600 ml-2"><?= date('h:i A', strtotime($pt['appointment_time'])) ?></span></td>
                                            <td class="p-4 font-bold text-blue-800"><?= htmlspecialchars($pt['full_name']) ?> <span class="text-xs font-normal text-gray-500 ml-1">(<?= htmlspecialchars($pt['course'] ?? '') ?>)</span></td>
                                            <td class="p-4 font-bold text-pup-maroon"><?= htmlspecialchars($pt['dental_procedure'] ?? 'General Checkup') ?></td>
                                            <td class="p-4">
                                                <span class="px-3 py-1 bg-blue-50 text-blue-700 rounded-full text-xs font-bold border border-blue-200 flex w-fit items-center gap-1"><i data-lucide="smartphone" class="h-3 w-3"></i> Needs Phone Verif.</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="p-12 text-center text-gray-500 font-medium">No approved dental appointments waiting.</td></tr>
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