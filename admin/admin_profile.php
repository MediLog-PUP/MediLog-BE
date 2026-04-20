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

try {
    $pdo->exec("ALTER TABLE users MODIFY profile_pic LONGTEXT");
} catch (PDOException $e) {}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle Form Details Update
    if(isset($_POST['first_name'])) {
        $first_name = $_POST['first_name'] ?? '';
        $last_name = $_POST['last_name'] ?? '';
        $full_name = trim($first_name . ' ' . $last_name);
        $phone_number = $_POST['phone_number'] ?? '';
        
        $updateStmt = $pdo->prepare("UPDATE users SET full_name=?, phone_number=? WHERE id=?");
        if($updateStmt->execute([$full_name, $phone_number, $user_id])) {
            $success_msg = "Profile updated successfully!";
        }
    }

    // Handle Profile Picture Upload (Cropped Base64 or standard file fallback)
    if (isset($_POST['cropped_image_data']) && !empty($_POST['cropped_image_data'])) {
        $base64_string = $_POST['cropped_image_data'];
        
        $image_parts = explode(";base64,", $base64_string);
        $image_type_aux = explode("image/", $image_parts[0]);
        $image_type = $image_type_aux[1] ?? 'jpeg';
        $image_base64 = base64_decode($image_parts[1]);

        $upload_dir = '../uploads/profiles/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $new_filename = 'admin_' . $user_id . '_' . time() . '.' . $image_type;

        if (file_put_contents($upload_dir . $new_filename, $image_base64)) {
            $updatePicStmt = $pdo->prepare("UPDATE users SET profile_pic=? WHERE id=?");
            $updatePicStmt->execute([$new_filename, $user_id]);
            $success_msg = "Profile picture updated successfully!";
        } else {
            $error_msg = "Failed to save the cropped image to the server.";
        }
    } else if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $file_tmp = $_FILES['profile_pic']['tmp_name'];
        $file_type = mime_content_type($file_tmp);
        $file_size = $_FILES['profile_pic']['size'];
        
        if (strpos($file_type, 'image/') === 0 && $file_size < 5000000) { 
            $upload_dir = '../uploads/profiles/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $file_ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
            $new_filename = 'admin_' . $user_id . '_' . time() . '.' . $file_ext;
            
            if (move_uploaded_file($file_tmp, $upload_dir . $new_filename)) {
                $updatePicStmt = $pdo->prepare("UPDATE users SET profile_pic=? WHERE id=?");
                $updatePicStmt->execute([$new_filename, $user_id]);
                $success_msg = "Profile picture updated successfully!";
            } else {
                $error_msg = "Failed to save the image to the server.";
            }
        } else {
            $error_msg = "Invalid file type or size too large (max 5MB).";
        }
    }
}

// Fetch Admin Profile Data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$name_parts = explode(' ', $user['full_name'], 2);
$first_name = $name_parts[0] ?? '';
$last_name = $name_parts[1] ?? '';

$profile_pic = 'https://ui-avatars.com/api/?name=' . urlencode($user['full_name']) . '&background=880000&color=fff';
if (!empty($user['profile_pic']) && $user['profile_pic'] !== 'default.png') {
    if (strpos($user['profile_pic'], 'data:image') === 0) {
        $profile_pic = $user['profile_pic'];
    } else {
        $profile_pic = '../uploads/profiles/' . htmlspecialchars($user['profile_pic']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - MediLog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- Cropper.js for image cropping -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

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
        /* Cropper customization */
        .cropper-view-box, .cropper-face { border-radius: 50%; }
    </style>
</head>
<body class="font-sans antialiased text-gray-800 bg-gray-50 flex h-screen overflow-hidden">

    <?php include '../global_loader.php'; ?>

    <aside class="hidden md:flex flex-col w-64 bg-gray-900 text-white h-full shadow-xl z-30 flex-shrink-0 relative">
        <div class="p-6 flex items-center gap-3 border-b border-gray-800">
            <div class="bg-pup-gold text-gray-900 p-2 rounded-lg"><i data-lucide="shield-plus" class="h-6 w-6"></i></div>
            <span class="font-bold text-xl tracking-tight text-white">MediLog Admin</span>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto no-scrollbar">
            <a href="admin_dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="layout-dashboard" class="h-5 w-5"></i> Overview</a>
            <a href="medicine_inventory.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="pill" class="h-5 w-5"></i> Inventory</a>
            <a href="patient_records.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="users" class="h-5 w-5"></i> Patient Records</a>
            <a href="admin_treatment_records.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="clipboard-list" class="h-5 w-5"></i> Treatment Records</a>
            <a href="admin_appointments.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="calendar" class="h-5 w-5"></i> Appointments</a>
            <a href="admin_clearance.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="file-check-2" class="h-5 w-5"></i> Clearances</a>
            <a href="admin_inquiries.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="message-square" class="h-5 w-5"></i> Inquiries</a>
            <a href="admin_profile.php" class="flex items-center gap-3 px-4 py-3 bg-pup-maroon text-white rounded-xl font-medium transition-colors shadow-sm"><i data-lucide="user-cog" class="h-5 w-5"></i> Profile</a>
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
            <a href="admin_treatment_records.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="clipboard-list" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Treatments</span></a>
            <a href="admin_appointments.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="calendar" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Schedule</span></a>
            <a href="admin_clearance.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="file-check-2" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Clearances</span></a>
            <a href="admin_inquiries.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="message-square" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Inquiries</span></a>
            <a href="admin_profile.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-pup-maroon transition-colors"><i data-lucide="user-cog" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Profile</span></a>
            <a href="super_admin_users.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="shield-alert" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Admins</span></a>
        </div>
    </nav>

    <main class="flex-1 flex flex-col h-full overflow-hidden">
        <header class="bg-white border-b border-gray-200 px-4 sm:px-8 py-4 flex items-center justify-between z-10 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="md:hidden bg-pup-maroon text-white p-1.5 rounded-lg mr-2"><i data-lucide="shield-plus" class="h-5 w-5 text-pup-gold"></i></div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900">Admin Profile</h1>
            </div>
            <div class="flex items-center gap-3 sm:gap-4">
                <a href="admin_notifications.php" class="text-gray-500 hover:text-pup-maroon p-2 bg-gray-50 hover:bg-red-50 rounded-full border border-gray-200 relative"><i data-lucide="bell" class="h-5 w-5"></i></a>
                <button onclick="openLogoutModal()" class="md:hidden text-gray-500 hover:text-red-600 transition-colors p-2 bg-gray-50 rounded-full border border-gray-200"><i data-lucide="log-out" class="h-5 w-5"></i></button>
                <div class="hidden md:flex items-center gap-3 pl-4 border-l border-gray-200">
                    <?php if($_SESSION['role'] === 'super_admin'): ?>
                        <span class="text-sm font-semibold text-white bg-purple-600 px-3 py-1 rounded-full mr-2"><i data-lucide="shield-alert" class="h-4 w-4 inline mr-1"></i> Super Admin</span>
                    <?php else: ?>
                        <span class="text-sm font-semibold text-gray-700 bg-gray-100 px-3 py-1 rounded-full mr-2"><i data-lucide="shield-check" class="h-4 w-4 inline mr-1 text-green-600"></i> Clinic Admin</span>
                    <?php endif; ?>
                    <div class="text-right"><p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($user['full_name']) ?></p></div>
                    <img src="<?= $profile_pic ?>" alt="Profile" class="h-10 w-10 rounded-full border-2 border-gray-200 object-cover">
                </div>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-4 sm:p-8 pb-24 md:pb-8">
            <div class="max-w-4xl mx-auto space-y-6">
                
                <?php if($success_msg): ?>
                    <div class="bg-green-50 text-green-700 p-4 rounded-xl text-sm font-medium border border-green-200 flex items-center gap-2"><i data-lucide="check-circle-2" class="h-5 w-5"></i> <?= htmlspecialchars($success_msg) ?></div>
                <?php endif; ?>
                <?php if($error_msg): ?>
                    <div class="bg-red-50 text-red-700 p-4 rounded-xl text-sm font-medium border border-red-200 flex items-center gap-2"><i data-lucide="alert-circle" class="h-5 w-5"></i> <?= htmlspecialchars($error_msg) ?></div>
                <?php endif; ?>

                <form id="profileForm" action="admin_profile.php" method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 sm:p-8">
                    
                    <!-- Hidden field to hold cropped image base64 data -->
                    <input type="hidden" name="cropped_image_data" id="cropped_image_data">

                    <div class="flex flex-col sm:flex-row items-center gap-6 mb-8 pb-6 border-b border-gray-100">
                        <div class="relative">
                            <img id="profileImagePreview" src="<?= $profile_pic ?>" alt="Profile Picture" class="w-24 h-24 rounded-full object-cover border-4 border-white shadow-md">
                            <label for="profile_pic_upload" class="absolute bottom-0 right-0 bg-pup-maroon hover:bg-pup-maroonDark text-white p-2 rounded-full cursor-pointer shadow-sm transition-colors border-2 border-white">
                                <i data-lucide="camera" class="h-4 w-4"></i>
                            </label>
                            <input type="file" id="profile_pic_upload" name="profile_pic" class="hidden" accept="image/jpeg, image/png, image/gif" onchange="openCropModal(event)">
                        </div>
                        <div class="text-center sm:text-left">
                            <h3 class="text-lg font-bold text-gray-900">Admin Photo</h3>
                            <p class="text-sm text-gray-500 mt-1">JPG, GIF or PNG. Max size of 5MB.</p>
                            <p class="text-xs text-gray-400 mt-1 italic">Click the camera icon to adjust and crop your new photo.</p>
                        </div>
                    </div>

                    <h3 class="text-lg font-bold text-gray-900 mb-4">Account Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-8">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Employee ID</label>
                            <input type="text" value="<?= htmlspecialchars($user['id_number']) ?>" class="block w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-50 text-gray-500 sm:text-sm cursor-not-allowed" readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                            <input type="email" value="<?= htmlspecialchars($user['email']) ?>" class="block w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-50 text-gray-500 sm:text-sm cursor-not-allowed" readonly>
                        </div>
                    </div>

                    <h3 class="text-lg font-bold text-gray-900 mb-4 pt-4 border-t border-gray-100">Personal Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-8">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($first_name) ?>" required class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($last_name) ?>" required class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                            <input type="text" name="phone_number" value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>" class="block w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                        </div>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-gray-100">
                        <button type="submit" class="bg-pup-maroon hover:bg-pup-maroonDark text-white px-8 py-3 rounded-xl font-semibold flex items-center transition-colors shadow-sm w-full sm:w-auto justify-center">
                            Save Changes <i data-lucide="save" class="ml-2 h-5 w-5"></i>
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </main>

    <!-- Crop Image Modal -->
    <div id="cropModal" class="fixed inset-0 z-[100] hidden bg-gray-900 bg-opacity-75 flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl flex flex-col">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-900">Crop Profile Picture</h3>
                <button type="button" onclick="closeCropModal()" class="text-gray-400 hover:text-gray-600 focus:outline-none"><i data-lucide="x" class="h-5 w-5"></i></button>
            </div>
            <div class="p-6 bg-gray-100 flex justify-center items-center" style="height: 400px;">
                <div class="w-full h-full">
                    <img id="imageToCrop" src="" alt="Picture" class="max-w-full max-h-full block mx-auto">
                </div>
            </div>
            <div class="bg-white px-6 py-4 flex justify-end gap-3 border-t border-gray-100">
                <button type="button" onclick="closeCropModal()" class="px-4 py-2 bg-white border border-gray-300 rounded-xl text-sm font-bold text-gray-700 hover:bg-gray-50 transition-colors">Cancel</button>
                <button type="button" onclick="applyCrop()" class="px-4 py-2 bg-pup-maroon text-white rounded-xl text-sm font-bold hover:bg-red-900 flex items-center gap-2 transition-colors">Apply & Save <i data-lucide="check" class="h-4 w-4"></i></button>
            </div>
        </div>
    </div>

    <!-- Logout Modal -->
    <div id="logoutModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div id="logoutModalOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity opacity-0 duration-300" onclick="closeLogoutModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div id="logoutModalPanel" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-sm w-full opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95 duration-300">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10"><i data-lucide="log-out" class="h-6 w-6 text-red-600"></i></div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left"><h3 class="text-lg leading-6 font-bold text-gray-900">Sign Out</h3><div class="mt-2"><p class="text-sm text-gray-500">Are you sure you want to sign out?</p></div></div>
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

        let cropper = null;
        const imageToCrop = document.getElementById('imageToCrop');
        const cropModal = document.getElementById('cropModal');
        const profilePicInput = document.getElementById('profile_pic_upload');
        const croppedDataInput = document.getElementById('cropped_image_data');
        const profileForm = document.getElementById('profileForm');

        function openCropModal(event) {
            const files = event.target.files;
            if (files && files.length > 0) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imageToCrop.src = e.target.result;
                    cropModal.classList.remove('hidden');
                    
                    if (cropper) {
                        cropper.destroy();
                    }
                    
                    setTimeout(() => {
                        cropper = new Cropper(imageToCrop, {
                            aspectRatio: 1, 
                            viewMode: 1,
                            autoCropArea: 1,
                            dragMode: 'move',
                            guides: false,
                            center: true,
                            highlight: false,
                            cropBoxMovable: true,
                            cropBoxResizable: true,
                        });
                    }, 100);
                };
                reader.readAsDataURL(files[0]);
            }
        }

        function closeCropModal() {
            cropModal.classList.add('hidden');
            profilePicInput.value = ''; 
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
        }

        function applyCrop() {
            if (!cropper) return;
            
            const canvas = cropper.getCroppedCanvas({
                width: 400,
                height: 400
            });
            
            const base64Data = canvas.toDataURL('image/jpeg', 0.9);
            
            croppedDataInput.value = base64Data;
            document.getElementById('profileImagePreview').src = base64Data;
            
            closeCropModal();
            profileForm.submit();
        }
    </script>
</body>
</html>