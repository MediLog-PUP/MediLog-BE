<?php
session_start();
require '../db_connect.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'faculty'])) {
    header("Location: ../auth/facultylogin.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_msg = '';

// Handle Patient Actions (Edit / Delete)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'], $_POST['patient_id'])) {
    $action = $_POST['action'];
    $p_id = intval($_POST['patient_id']);

    if ($action === 'edit') {
        $full_name = trim($_POST['full_name'] ?? '');
        $course = trim($_POST['course'] ?? '');
        $section = trim($_POST['section'] ?? '');
        $blood_type = trim($_POST['blood_type'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');
        
        // New fields for comprehensive editing
        $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
        $gender = !empty($_POST['gender']) ? trim($_POST['gender']) : null;
        $address = !empty($_POST['address']) ? trim($_POST['address']) : null;
        $allergies = !empty($_POST['allergies']) ? trim($_POST['allergies']) : null;
        $em_name = !empty($_POST['emergency_contact_name']) ? trim($_POST['emergency_contact_name']) : null;
        $em_phone = !empty($_POST['emergency_contact_phone']) ? trim($_POST['emergency_contact_phone']) : null;
        
        $updateStmt = $pdo->prepare("UPDATE users SET full_name=?, course=?, section=?, blood_type=?, phone_number=?, dob=?, gender=?, address=?, allergies=?, emergency_contact_name=?, emergency_contact_phone=? WHERE id=? AND role='student'");
        if ($updateStmt->execute([$full_name, $course, $section, $blood_type, $phone_number, $dob, $gender, $address, $allergies, $em_name, $em_phone, $p_id])) {
            $success_msg = "Patient record updated successfully.";
        }
    } elseif ($action === 'delete') {
        $delStmt = $pdo->prepare("DELETE FROM users WHERE id=? AND role='student'");
        if ($delStmt->execute([$p_id])) {
            $success_msg = "Patient record deleted successfully.";
        }
    }
}

// Fetch Admin Info
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

// Fetch all students
try {
    $stmt = $pdo->query("SELECT * FROM users WHERE role = 'student' ORDER BY created_at DESC");
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error fetching records: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Records - MediLog</title>
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

    <aside class="hidden md:flex flex-col w-64 bg-gray-900 text-white h-full shadow-xl z-20 flex-shrink-0">
        <div class="p-6 flex items-center gap-3 border-b border-gray-800">
            <div class="bg-pup-gold text-gray-900 p-2 rounded-lg"><i data-lucide="shield-plus" class="h-6 w-6"></i></div>
            <span class="font-bold text-xl tracking-tight text-white">MediLog Admin</span>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <a href="admin_dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="layout-dashboard" class="h-5 w-5"></i> Overview</a>
            <a href="medicine_inventory.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="pill" class="h-5 w-5"></i> Inventory</a>
            <a href="patient_records.php" class="flex items-center gap-3 px-4 py-3 bg-pup-maroon text-white rounded-xl font-medium transition-colors shadow-sm"><i data-lucide="users" class="h-5 w-5"></i> Patient Records</a>
            <a href="admin_appointments.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="calendar" class="h-5 w-5"></i> Appointments</a>
            <a href="admin_clearance.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="file-check-2" class="h-5 w-5"></i> Clearances</a>
            <a href="admin_inquiries.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="message-square" class="h-5 w-5"></i> Inquiries</a>
            <a href="admin_profile.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="user-cog" class="h-5 w-5"></i> Profile</a>
        </nav>
        <div class="p-4 border-t border-gray-800">
            <button onclick="openLogoutModal()" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-red-900/50 hover:text-red-400 rounded-xl font-medium transition-colors w-full text-left"><i data-lucide="log-out" class="h-5 w-5"></i> Sign Out</button>
        </div>
    </aside>

    <nav class="md:hidden fixed bottom-0 left-0 w-full bg-white border-t border-gray-200 z-50 overflow-x-auto no-scrollbar shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
        <div class="flex items-center w-max px-2 min-w-full justify-between">
            <a href="admin_dashboard.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="layout-dashboard" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Home</span></a>
            <a href="medicine_inventory.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="pill" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Inventory</span></a>
            <a href="patient_records.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-pup-maroon transition-colors"><i data-lucide="users" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Patients</span></a>
            <a href="admin_appointments.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="calendar" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Schedule</span></a>
            <a href="admin_clearance.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="file-check-2" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Clearances</span></a>
            <a href="admin_inquiries.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="message-square" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Inquiries</span></a>
            <a href="admin_profile.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="user-cog" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Profile</span></a>
        </div>
    </nav>

    <main class="flex-1 flex flex-col h-full overflow-hidden">
        <header class="bg-white border-b border-gray-200 px-4 sm:px-8 py-4 flex items-center justify-between z-10 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="md:hidden bg-pup-maroon text-white p-1.5 rounded-lg mr-2"><i data-lucide="shield-plus" class="h-5 w-5 text-pup-gold"></i></div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900">Patient Records</h1>
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
            <div class="max-w-7xl mx-auto space-y-6">
                
                <?php if($success_msg): ?>
                    <div class="bg-green-50 text-green-700 p-4 rounded-xl text-sm font-medium border border-green-200 flex items-center gap-2 mb-6">
                        <i data-lucide="check-circle-2" class="h-5 w-5"></i> <?= htmlspecialchars($success_msg) ?>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider border-b border-gray-200">
                                    <th class="p-4 font-semibold">Student Details</th>
                                    <th class="p-4 font-semibold">Course & Section</th>
                                    <th class="p-4 font-semibold">Blood Type</th>
                                    <th class="p-4 font-semibold">Contact Info</th>
                                    <th class="p-4 font-semibold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-sm">
                                <?php if(count($patients) > 0): ?>
                                    <?php foreach($patients as $patient): 
                                        $p_pic = 'https://ui-avatars.com/api/?name=' . urlencode($patient['full_name']) . '&background=880000&color=fff';
                                        if (!empty($patient['profile_pic']) && $patient['profile_pic'] !== 'default.png') {
                                            if (strpos($patient['profile_pic'], 'data:image') === 0) {
                                                $p_pic = $patient['profile_pic'];
                                            } else {
                                                $p_pic = '../uploads/profiles/' . htmlspecialchars($patient['profile_pic']);
                                            }
                                        }
                                        
                                        // Prepare safe patient data for JSON serialization
                                        $safe_patient = $patient;
                                        unset($safe_patient['password']); // Securely remove password hash
                                        $safe_patient['resolved_pic'] = $p_pic; // Pass the resolved image URL directly to JS
                                    ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="p-4">
                                                <div class="flex items-center gap-3">
                                                    <img src="<?= $p_pic ?>" class="w-10 h-10 rounded-full object-cover border border-gray-200 shadow-sm">
                                                    <div>
                                                        <div class="font-bold text-gray-900"><?= htmlspecialchars($patient['full_name']) ?></div>
                                                        <div class="text-xs text-gray-500"><?= htmlspecialchars($patient['id_number']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="p-4 font-medium text-gray-700"><?= htmlspecialchars($patient['course'] ?? 'N/A') ?> <?= htmlspecialchars($patient['section'] ?? '') ?></td>
                                            <td class="p-4 text-red-600 font-semibold"><?= htmlspecialchars($patient['blood_type'] ?? 'Unknown') ?></td>
                                            <td class="p-4">
                                                <div class="text-gray-900"><?= htmlspecialchars($patient['phone_number'] ?? 'N/A') ?></div>
                                                <div class="text-xs text-gray-500"><?= htmlspecialchars($patient['email'] ?? '') ?></div>
                                            </td>
                                            <td class="p-4 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <button onclick="openViewModal(this)" data-patient='<?= htmlspecialchars(json_encode($safe_patient), ENT_QUOTES, 'UTF-8') ?>' class="p-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition-colors" title="View Details"><i data-lucide="eye" class="h-4 w-4"></i></button>
                                                    <button onclick="openEditModal(this)" data-patient='<?= htmlspecialchars(json_encode($safe_patient), ENT_QUOTES, 'UTF-8') ?>' class="p-2 bg-yellow-50 text-yellow-600 rounded-lg hover:bg-yellow-100 transition-colors" title="Edit Record"><i data-lucide="edit-2" class="h-4 w-4"></i></button>
                                                    <button onclick="openDeleteModal(<?= $patient['id'] ?>)" class="p-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors" title="Delete Record"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="p-8 text-center text-gray-500">No patient records found in the database.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- View Patient Modal -->
    <div id="viewModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div id="viewModalOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity opacity-0 duration-300" onclick="closeViewModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div id="viewModalPanel" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95 duration-300">
                <div class="bg-white px-6 pt-6 pb-6 border-b border-gray-100">
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="text-xl font-bold text-gray-900">Patient Details</h3>
                        <button type="button" onclick="closeViewModal()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-6 w-6"></i></button>
                    </div>
                    <div class="space-y-4 text-sm">
                        <div class="grid grid-cols-2 gap-4 bg-gray-50 p-4 rounded-xl">
                            <div><p class="text-xs text-gray-500 font-semibold mb-1">Full Name</p><p class="font-bold text-gray-900" id="view-name"></p></div>
                            <div><p class="text-xs text-gray-500 font-semibold mb-1">Student ID</p><p class="font-bold text-gray-900" id="view-id"></p></div>
                            <div class="col-span-2"><p class="text-xs text-gray-500 font-semibold mb-1">Email</p><p class="font-medium text-gray-900" id="view-email"></p></div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><p class="text-xs text-gray-500 font-semibold mb-1">Course & Section</p><p class="font-medium text-gray-900" id="view-course"></p></div>
                            <div><p class="text-xs text-gray-500 font-semibold mb-1">Phone Number</p><p class="font-medium text-gray-900" id="view-phone"></p></div>
                            <div class="col-span-2"><p class="text-xs text-gray-500 font-semibold mb-1">Address</p><p class="font-medium text-gray-900" id="view-address"></p></div>
                        </div>
                        <div class="grid grid-cols-3 gap-4 border-t border-gray-100 pt-4">
                            <div><p class="text-xs text-gray-500 font-semibold mb-1">Date of Birth</p><p class="font-medium text-gray-900" id="view-dob"></p></div>
                            <div><p class="text-xs text-gray-500 font-semibold mb-1">Gender</p><p class="font-medium text-gray-900" id="view-gender"></p></div>
                            <div><p class="text-xs text-gray-500 font-semibold mb-1">Blood Type</p><p class="font-bold text-red-600" id="view-blood"></p></div>
                        </div>
                        <div class="border-t border-gray-100 pt-4">
                            <p class="text-xs text-gray-500 font-semibold mb-1">Known Allergies</p>
                            <p class="font-medium text-gray-900" id="view-allergies"></p>
                        </div>
                        <div class="grid grid-cols-2 gap-4 border-t border-gray-100 pt-4">
                            <div><p class="text-xs text-gray-500 font-semibold mb-1">Emergency Contact</p><p class="font-medium text-gray-900" id="view-em-name"></p></div>
                            <div><p class="text-xs text-gray-500 font-semibold mb-1">Emergency Phone</p><p class="font-medium text-gray-900" id="view-em-phone"></p></div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-6 py-4 flex justify-end">
                    <button type="button" onclick="closeViewModal()" class="inline-flex justify-center rounded-xl border border-gray-300 shadow-sm px-6 py-2.5 bg-white text-sm font-bold text-gray-700 hover:bg-gray-50 transition-colors">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Patient Modal -->
    <div id="editModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div id="editModalOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity opacity-0 duration-300" onclick="closeEditModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div id="editModalPanel" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95 duration-300">
                <form action="patient_records.php" method="POST" class="flex flex-col max-h-[90vh]">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="patient_id" id="edit-id">
                    
                    <div class="px-6 pt-6 pb-4 border-b border-gray-100 flex-shrink-0">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xl font-bold text-gray-900">Edit Patient Record</h3>
                            <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-6 w-6"></i></button>
                        </div>
                    </div>

                    <div class="px-6 py-6 overflow-y-auto space-y-4">
                        <!-- Profile Picture Display -->
                        <div class="flex items-center gap-4 mb-2 pb-4 border-b border-gray-100">
                            <img id="edit-pic" src="" alt="Profile" class="w-16 h-16 rounded-full object-cover border-2 border-gray-200 shadow-sm">
                            <div>
                                <p class="text-sm font-bold text-gray-900">Student Photo</p>
                                <p class="text-xs text-gray-500">Cannot be modified here.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                                <input type="text" name="full_name" id="edit-name" required class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                                <input type="email" id="edit-email" readonly class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl bg-gray-50 text-gray-500 sm:text-sm cursor-not-allowed" title="Email cannot be edited">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Course</label>
                                <input type="text" name="course" id="edit-course" class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                                <input type="text" name="section" id="edit-section" class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                                <input type="date" name="dob" id="edit-dob" class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                                <select name="gender" id="edit-gender" class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm bg-white">
                                    <option value="">Select</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                                <input type="text" name="phone_number" id="edit-phone" class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Blood Type</label>
                                <select name="blood_type" id="edit-blood" class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm bg-white">
                                    <option value="">Unknown</option>
                                    <option value="A+">A+</option><option value="A-">A-</option>
                                    <option value="B+">B+</option><option value="B-">B-</option>
                                    <option value="AB+">AB+</option><option value="AB-">AB-</option>
                                    <option value="O+">O+</option><option value="O-">O-</option>
                                </select>
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                                <textarea name="address" id="edit-address" rows="2" class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm"></textarea>
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Known Allergies</label>
                                <input type="text" name="allergies" id="edit-allergies" class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact</label>
                                <input type="text" name="emergency_contact_name" id="edit-em-name" class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Emergency Phone</label>
                                <input type="text" name="emergency_contact_phone" id="edit-em-phone" class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 px-6 py-4 flex flex-col sm:flex-row-reverse gap-3 flex-shrink-0 rounded-b-2xl border-t border-gray-100">
                        <button type="submit" class="w-full sm:w-auto inline-flex justify-center rounded-xl shadow-sm px-6 py-2.5 bg-pup-maroon text-sm font-bold text-white hover:bg-pup-maroonDark transition-colors">Save Changes</button>
                        <button type="button" onclick="closeEditModal()" class="w-full sm:w-auto inline-flex justify-center rounded-xl border border-gray-300 shadow-sm px-6 py-2.5 bg-white text-sm font-bold text-gray-700 hover:bg-gray-50 transition-colors">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div id="deleteModalOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity opacity-0 duration-300" onclick="closeDeleteModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div id="deleteModalPanel" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-sm w-full opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95 duration-300">
                <form action="patient_records.php" method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="patient_id" id="delete-id">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10"><i data-lucide="alert-triangle" class="h-6 w-6 text-red-600"></i></div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-bold text-gray-900">Delete Patient Record</h3>
                                <div class="mt-2"><p class="text-sm text-gray-500">Are you sure you want to permanently delete this student's record? This action cannot be undone.</p></div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 flex flex-col sm:flex-row-reverse gap-2">
                        <button type="submit" class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-bold text-white hover:bg-red-700 sm:w-auto sm:text-sm transition-colors text-center">Delete</button>
                        <button type="button" onclick="closeDeleteModal()" class="w-full inline-flex justify-center rounded-xl border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-bold text-gray-700 hover:bg-gray-50 sm:w-auto sm:text-sm transition-colors">Cancel</button>
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

        // View Modal Logic
        function openViewModal(btn) {
            const data = JSON.parse(btn.getAttribute('data-patient'));
            document.getElementById('view-name').textContent = data.full_name || 'N/A';
            document.getElementById('view-id').textContent = data.id_number || 'N/A';
            document.getElementById('view-email').textContent = data.email || 'N/A';
            document.getElementById('view-course').textContent = (data.course || 'N/A') + ' ' + (data.section || '');
            document.getElementById('view-phone').textContent = data.phone_number || 'N/A';
            document.getElementById('view-address').textContent = data.address || 'N/A';
            document.getElementById('view-dob').textContent = data.dob || 'N/A';
            document.getElementById('view-gender').textContent = data.gender || 'N/A';
            document.getElementById('view-blood').textContent = data.blood_type || 'Unknown';
            document.getElementById('view-allergies').textContent = data.allergies || 'None';
            document.getElementById('view-em-name').textContent = data.emergency_contact_name || 'N/A';
            document.getElementById('view-em-phone').textContent = data.emergency_contact_phone || 'N/A';

            const m = document.getElementById('viewModal');
            m.classList.remove('hidden');
            setTimeout(() => {
                document.getElementById('viewModalOverlay').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('viewModalPanel').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('viewModalPanel').classList.replace('translate-y-4', 'translate-y-0');
                document.getElementById('viewModalPanel').classList.replace('sm:scale-95', 'sm:scale-100');
            }, 10);
        }
        function closeViewModal() {
            document.getElementById('viewModalOverlay').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('viewModalPanel').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('viewModalPanel').classList.replace('translate-y-0', 'translate-y-4');
            document.getElementById('viewModalPanel').classList.replace('sm:scale-100', 'sm:scale-95');
            setTimeout(() => document.getElementById('viewModal').classList.add('hidden'), 300);
        }

        // Edit Modal Logic
        function openEditModal(btn) {
            const data = JSON.parse(btn.getAttribute('data-patient'));
            document.getElementById('edit-id').value = data.id;
            
            // Populate photo and all details
            document.getElementById('edit-pic').src = data.resolved_pic || 'https://ui-avatars.com/api/?name=Student&background=880000&color=fff';
            document.getElementById('edit-email').value = data.email || '';
            document.getElementById('edit-name').value = data.full_name || '';
            document.getElementById('edit-course').value = data.course || '';
            document.getElementById('edit-section').value = data.section || '';
            document.getElementById('edit-phone').value = data.phone_number || '';
            document.getElementById('edit-blood').value = data.blood_type || '';
            document.getElementById('edit-dob').value = data.dob || '';
            document.getElementById('edit-gender').value = data.gender || '';
            document.getElementById('edit-address').value = data.address || '';
            document.getElementById('edit-allergies').value = data.allergies || '';
            document.getElementById('edit-em-name').value = data.emergency_contact_name || '';
            document.getElementById('edit-em-phone').value = data.emergency_contact_phone || '';

            const m = document.getElementById('editModal');
            m.classList.remove('hidden');
            setTimeout(() => {
                document.getElementById('editModalOverlay').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('editModalPanel').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('editModalPanel').classList.replace('translate-y-4', 'translate-y-0');
                document.getElementById('editModalPanel').classList.replace('sm:scale-95', 'sm:scale-100');
            }, 10);
        }
        function closeEditModal() {
            document.getElementById('editModalOverlay').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('editModalPanel').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('editModalPanel').classList.replace('translate-y-0', 'translate-y-4');
            document.getElementById('editModalPanel').classList.replace('sm:scale-100', 'sm:scale-95');
            setTimeout(() => document.getElementById('editModal').classList.add('hidden'), 300);
        }

        // Delete Modal Logic
        function openDeleteModal(id) {
            document.getElementById('delete-id').value = id;
            const m = document.getElementById('deleteModal');
            m.classList.remove('hidden');
            setTimeout(() => {
                document.getElementById('deleteModalOverlay').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('deleteModalPanel').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('deleteModalPanel').classList.replace('translate-y-4', 'translate-y-0');
                document.getElementById('deleteModalPanel').classList.replace('sm:scale-95', 'sm:scale-100');
            }, 10);
        }
        function closeDeleteModal() {
            document.getElementById('deleteModalOverlay').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('deleteModalPanel').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('deleteModalPanel').classList.replace('translate-y-0', 'translate-y-4');
            document.getElementById('deleteModalPanel').classList.replace('sm:scale-100', 'sm:scale-95');
            setTimeout(() => document.getElementById('deleteModal').classList.add('hidden'), 300);
        }

        // Logout Modals & Routing
        function openLogoutModal() { const m = document.getElementById('logoutModal'); m.classList.remove('hidden'); setTimeout(() => { document.getElementById('logoutModalOverlay').classList.replace('opacity-0', 'opacity-100'); document.getElementById('logoutModalPanel').classList.replace('opacity-0', 'opacity-100'); document.getElementById('logoutModalPanel').classList.replace('translate-y-4', 'translate-y-0'); document.getElementById('logoutModalPanel').classList.replace('sm:scale-95', 'sm:scale-100'); }, 10); }
        function closeLogoutModal() { document.getElementById('logoutModalOverlay').classList.replace('opacity-100', 'opacity-0'); document.getElementById('logoutModalPanel').classList.replace('opacity-100', 'opacity-0'); document.getElementById('logoutModalPanel').classList.replace('translate-y-0', 'translate-y-4'); document.getElementById('logoutModalPanel').classList.replace('sm:scale-100', 'sm:scale-95'); setTimeout(() => document.getElementById('logoutModal').classList.add('hidden'), 300); }
        document.addEventListener('DOMContentLoaded', () => { document.querySelectorAll('a[href]:not([href^="#"]):not([target="_blank"])').forEach(link => { link.addEventListener('click', e => { const href = link.getAttribute('href'); if (!href || href === "javascript:void(0);") return; e.preventDefault(); document.body.classList.add('page-exit'); setTimeout(() => window.location.href = href, 250); }); }); });
    </script>
</body>
</html>