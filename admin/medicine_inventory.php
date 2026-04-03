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

// Handle AJAX Requests (Real-time updates)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax'])) {
    if (isset($_POST['action']) && isset($_POST['medicine_id'])) {
        $med_id = intval($_POST['medicine_id']);
        $action = $_POST['action'];

        if ($action === 'increase' || $action === 'reduce') {
            $adj = ($action === 'increase') ? 1 : -1;
            
            $stmt = $pdo->prepare("SELECT name, quantity FROM medicine_inventory WHERE id = ?");
            $stmt->execute([$med_id]);
            $med = $stmt->fetch();

            if ($med) {
                if ($action === 'reduce' && $med['quantity'] <= 0) {
                    $error_msg = "Error: '" . htmlspecialchars($med['name']) . "' is already out of stock! Cannot reduce further.";
                } else {
                    $new_qty = max(0, $med['quantity'] + $adj);
                    $new_status = ($new_qty > 20) ? 'In Stock' : (($new_qty > 0) ? 'Low Stock' : 'Out of Stock');

                    $updateStmt = $pdo->prepare("UPDATE medicine_inventory SET quantity = ?, status = ? WHERE id = ?");
                    $updateStmt->execute([$new_qty, $new_status, $med_id]);
                    
                    $adminName = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
                    $adminName->execute([$user_id]);
                    $admin_full_name = $adminName->fetchColumn();

                    $adj_text = ($action === 'increase') ? 'increased' : 'reduced';
                    $notif_msg = "[Inventory] Faculty " . $admin_full_name . " " . $adj_text . " the stock of " . $med['name'] . ".";
                    $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (NULL, ?)")->execute([$notif_msg]);

                    $success_msg = "Inventory quantity updated successfully.";
                }
            }
        } elseif ($action === 'edit_medicine') {
            $name = $_POST['name'];
            $category = $_POST['category'];
            $dosage = $_POST['dosage'];
            $quantity = intval($_POST['quantity']);
            $expiration_date = $_POST['expiration_date'];
            
            $status = ($quantity > 20) ? 'In Stock' : (($quantity > 0) ? 'Low Stock' : 'Out of Stock');

            $updateStmt = $pdo->prepare("UPDATE medicine_inventory SET name = ?, category = ?, dosage = ?, quantity = ?, status = ?, expiration_date = ? WHERE id = ?");
            if ($updateStmt->execute([$name, $category, $dosage, $quantity, $status, $expiration_date, $med_id])) {
                $adminName = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
                $adminName->execute([$user_id]);
                $admin_full_name = $adminName->fetchColumn();

                $notif_msg = "[Inventory] Faculty " . $admin_full_name . " edited the details of medicine: " . $name . ".";
                $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (NULL, ?)")->execute([$notif_msg]);

                $success_msg = "Medicine record updated successfully.";
            } else {
                $error_msg = "Failed to update the medicine record.";
            }
        } elseif ($action === 'delete_medicine') {
            $nameStmt = $pdo->prepare("SELECT name FROM medicine_inventory WHERE id = ?");
            $nameStmt->execute([$med_id]);
            $medName = $nameStmt->fetchColumn();

            $delStmt = $pdo->prepare("DELETE FROM medicine_inventory WHERE id = ?");
            if ($delStmt->execute([$med_id])) {
                $adminName = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
                $adminName->execute([$user_id]);
                $admin_full_name = $adminName->fetchColumn();

                $notif_msg = "[Inventory] Faculty " . $admin_full_name . " removed medicine: " . $medName . " from the inventory.";
                $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (NULL, ?)")->execute([$notif_msg]);

                $success_msg = "Medicine deleted successfully.";
            } else {
                $error_msg = "Failed to delete the medicine record.";
            }
        }
    } elseif (isset($_POST['name'], $_POST['category'])) {
        $name = $_POST['name'];
        $category = $_POST['category'];
        $dosage = $_POST['dosage'];
        $quantity = intval($_POST['quantity']);
        $expiration_date = $_POST['expiration_date'];
        
        $status = ($quantity > 20) ? 'In Stock' : (($quantity > 0) ? 'Low Stock' : 'Out of Stock');

        $stmt = $pdo->prepare("INSERT INTO medicine_inventory (name, category, dosage, quantity, status, expiration_date) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $category, $dosage, $quantity, $status, $expiration_date]);
        
        $adminName = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $adminName->execute([$user_id]);
        $admin_full_name = $adminName->fetchColumn();

        $notif_msg = "[Inventory] Faculty " . $admin_full_name . " added a new medicine: " . $name . ".";
        $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (NULL, ?)")->execute([$notif_msg]);

        $success_msg = "Medicine item added successfully!";
    }

    // Fetch the fresh inventory to send back
    $invStmt = $pdo->query("SELECT * FROM medicine_inventory ORDER BY name ASC");
    $inventory = $invStmt->fetchAll(PDO::FETCH_ASSOC);
    
    ob_start();
    if(count($inventory) > 0) {
        foreach($inventory as $item) {
            $statusHtml = '';
            if($item['status'] == 'In Stock' && $item['quantity'] > 20) {
                $statusHtml = '<span class="px-2.5 py-1 bg-green-50 text-green-700 rounded-full text-xs font-semibold border border-green-100">In Stock</span>';
            } elseif($item['quantity'] <= 20 && $item['quantity'] > 0) {
                $statusHtml = '<span class="px-2.5 py-1 bg-yellow-50 text-yellow-700 rounded-full text-xs font-semibold border border-yellow-100">Low Stock</span>';
            } else {
                $statusHtml = '<span class="px-2.5 py-1 bg-red-50 text-red-700 rounded-full text-xs font-semibold border border-red-100">Out of Stock</span>';
            }
            
            $jsonItem = htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8');
            $dateHtml = date("M d, Y", strtotime($item['expiration_date']));
            
            echo <<<HTML
            <tr class="hover:bg-gray-50">
                <td class="p-4 font-bold text-gray-900">{$item['name']}</td>
                <td class="p-4">
                    <div class="text-gray-900">{$item['category']}</div>
                    <div class="text-xs text-gray-500">{$item['dosage']}</div>
                </td>
                <td class="p-4 font-medium">
                    <div class="flex items-center justify-center gap-3">
                        <form onsubmit="handleInventoryAjax(event, this)" class="m-0 p-0">
                            <input type="hidden" name="medicine_id" value="{$item['id']}">
                            <input type="hidden" name="action" value="reduce">
                            <button type="submit" class="w-7 h-7 flex items-center justify-center bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors border border-red-100"><i data-lucide="minus" class="h-3 w-3"></i></button>
                        </form>
                        <span class="font-bold text-gray-900 w-8 text-center">{$item['quantity']}</span>
                        <form onsubmit="handleInventoryAjax(event, this)" class="m-0 p-0">
                            <input type="hidden" name="medicine_id" value="{$item['id']}">
                            <input type="hidden" name="action" value="increase">
                            <button type="submit" class="w-7 h-7 flex items-center justify-center bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition-colors border border-green-100"><i data-lucide="plus" class="h-3 w-3"></i></button>
                        </form>
                    </div>
                </td>
                <td class="p-4">{$statusHtml}</td>
                <td class="p-4 text-gray-500">{$dateHtml}</td>
                <td class="p-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <button onclick="openEditMedModal(this)" data-med='{$jsonItem}' class="p-2 bg-yellow-50 text-yellow-600 rounded-lg hover:bg-yellow-100 transition-colors" title="Edit Medicine"><i data-lucide="edit-2" class="h-4 w-4"></i></button>
                        <button onclick="openDeleteMedModal({$item['id']})" class="p-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors" title="Delete Medicine"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                    </div>
                </td>
            </tr>
HTML;
        }
    } else {
        echo '<tr><td colspan="6" class="p-8 text-center text-gray-500">No medicine inventory found.</td></tr>';
    }
    $html = ob_get_clean();

    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success_msg,
        'error' => $error_msg,
        'html' => $html
    ]);
    exit();
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

// Fetch Initial Inventory Data
try {
    $invStmt = $pdo->query("SELECT * FROM medicine_inventory ORDER BY name ASC");
    $inventory = $invStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error fetching inventory: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Inventory - MediLog</title>
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
            <div class="bg-pup-gold text-gray-900 p-2 rounded-lg"><i data-lucide="shield-plus" class="h-6 w-6"></i></div>
            <span class="font-bold text-xl tracking-tight text-white">MediLog Admin</span>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <a href="admin_dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="layout-dashboard" class="h-5 w-5"></i> Overview</a>
            <a href="medicine_inventory.php" class="flex items-center gap-3 px-4 py-3 bg-pup-maroon text-white rounded-xl font-medium transition-colors shadow-sm"><i data-lucide="pill" class="h-5 w-5"></i> Inventory</a>
            <a href="patient_records.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="users" class="h-5 w-5"></i> Patient Records</a>
            <a href="admin_treatment_records.php" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-gray-800 hover:text-white rounded-xl font-medium transition-colors"><i data-lucide="clipboard-list" class="h-5 w-5"></i> Treatment Records</a>
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
            <a href="medicine_inventory.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-pup-maroon transition-colors"><i data-lucide="pill" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Inventory</span></a>
            <a href="patient_records.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="users" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Patients</span></a>
            <a href="admin_treatment_records.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="clipboard-list" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Treatments</span></a>
            <a href="admin_appointments.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="calendar" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Schedule</span></a>
            <a href="admin_clearance.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="file-check-2" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Clearances</span></a>
            <a href="admin_inquiries.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="message-square" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Inquiries</span></a>
            <a href="admin_profile.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="user-cog" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Profile</span></a>
            <a href="super_admin_users.php" class="flex flex-col items-center p-2.5 min-w-[72px] text-gray-500 hover:text-pup-maroon transition-colors"><i data-lucide="shield-alert" class="h-5 w-5"></i><span class="text-[10px] font-medium mt-1">Admins</span></a>
        </div>
    </nav>

    <main class="flex-1 flex flex-col h-full overflow-hidden">
        <header class="bg-white border-b border-gray-200 px-4 sm:px-8 py-4 flex items-center justify-between z-10 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="md:hidden bg-pup-maroon text-white p-1.5 rounded-lg mr-2"><i data-lucide="shield-plus" class="h-5 w-5 text-pup-gold"></i></div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-900">Medicine Inventory</h1>
            </div>
            <div class="flex items-center gap-3 sm:gap-4">
                <a href="admin_notifications.php" class="text-gray-500 hover:text-pup-maroon p-2 bg-gray-50 hover:bg-red-50 rounded-full border border-gray-200 relative">
                    <i data-lucide="bell" class="h-5 w-5"></i>
                </a>
                <button onclick="openLogoutModal()" class="md:hidden text-gray-500 hover:text-red-600 transition-colors p-2 bg-gray-50 rounded-full border border-gray-200"><i data-lucide="log-out" class="h-5 w-5"></i></button>
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
                
                <div class="flex justify-between items-center">
                    <h2 class="text-lg font-bold text-gray-900">Current Stock Levels</h2>
                    <button onclick="openAddMedicineModal()" class="bg-pup-maroon hover:bg-pup-maroonDark text-white px-4 py-2 rounded-xl text-sm font-semibold shadow-sm flex items-center gap-2 transition-colors"><i data-lucide="plus" class="h-4 w-4"></i> Add Item</button>
                </div>

                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider border-b border-gray-200">
                                    <th class="p-4 font-semibold">Medicine Name</th>
                                    <th class="p-4 font-semibold">Category & Dosage</th>
                                    <th class="p-4 font-semibold text-center">Quantity</th>
                                    <th class="p-4 font-semibold">Status</th>
                                    <th class="p-4 font-semibold">Expiration</th>
                                    <th class="p-4 font-semibold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="inventory-tbody" class="divide-y divide-gray-100 text-sm">
                                <?php if(count($inventory) > 0): ?>
                                    <?php foreach($inventory as $item): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="p-4 font-bold text-gray-900"><?= htmlspecialchars($item['name']) ?></td>
                                            <td class="p-4">
                                                <div class="text-gray-900"><?= htmlspecialchars($item['category']) ?></div>
                                                <div class="text-xs text-gray-500"><?= htmlspecialchars($item['dosage']) ?></div>
                                            </td>
                                            <td class="p-4 font-medium">
                                                <div class="flex items-center justify-center gap-3">
                                                    <!-- Quantity Buttons AJAX Ready -->
                                                    <form onsubmit="handleInventoryAjax(event, this)" class="m-0 p-0">
                                                        <input type="hidden" name="medicine_id" value="<?= $item['id'] ?>">
                                                        <input type="hidden" name="action" value="reduce">
                                                        <button type="submit" class="w-7 h-7 flex items-center justify-center bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors border border-red-100"><i data-lucide="minus" class="h-3 w-3"></i></button>
                                                    </form>
                                                    <span class="font-bold text-gray-900 w-8 text-center"><?= htmlspecialchars($item['quantity']) ?></span>
                                                    <form onsubmit="handleInventoryAjax(event, this)" class="m-0 p-0">
                                                        <input type="hidden" name="medicine_id" value="<?= $item['id'] ?>">
                                                        <input type="hidden" name="action" value="increase">
                                                        <button type="submit" class="w-7 h-7 flex items-center justify-center bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition-colors border border-green-100"><i data-lucide="plus" class="h-3 w-3"></i></button>
                                                    </form>
                                                </div>
                                            </td>
                                            <td class="p-4">
                                                <?php if($item['status'] == 'In Stock' && $item['quantity'] > 20): ?>
                                                    <span class="px-2.5 py-1 bg-green-50 text-green-700 rounded-full text-xs font-semibold border border-green-100">In Stock</span>
                                                <?php elseif($item['quantity'] <= 20 && $item['quantity'] > 0): ?>
                                                    <span class="px-2.5 py-1 bg-yellow-50 text-yellow-700 rounded-full text-xs font-semibold border border-yellow-100">Low Stock</span>
                                                <?php else: ?>
                                                    <span class="px-2.5 py-1 bg-red-50 text-red-700 rounded-full text-xs font-semibold border border-red-100">Out of Stock</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-4 text-gray-500"><?= date("M d, Y", strtotime($item['expiration_date'])) ?></td>
                                            <td class="p-4 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <button onclick="openEditMedModal(this)" data-med='<?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?>' class="p-2 bg-yellow-50 text-yellow-600 rounded-lg hover:bg-yellow-100 transition-colors" title="Edit Medicine"><i data-lucide="edit-2" class="h-4 w-4"></i></button>
                                                    <button onclick="openDeleteMedModal(<?= $item['id'] ?>)" class="p-2 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition-colors" title="Delete Medicine"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="p-8 text-center text-gray-500">No medicine inventory found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Generic Pop-up Alert Modal (For Success and Errors) -->
    <div id="alertModal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div id="alertModalOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity opacity-0 duration-300" onclick="closeAlertModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div id="alertModalPanel" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-sm w-full opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95 duration-300">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div id="alertModalIconContainer" class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full sm:mx-0 sm:h-10 sm:w-10">
                            <!-- Icon gets injected here via JS -->
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-bold text-gray-900" id="alertModalTitle">Alert</h3>
                            <div class="mt-2"><p class="text-sm text-gray-500" id="alertModalMessage"></p></div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 flex flex-row-reverse">
                    <button type="button" onclick="closeAlertModal()" id="alertModalCloseBtn" class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-2 text-base font-bold text-white sm:w-auto sm:text-sm transition-colors text-center">Okay</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Medicine Modal -->
    <div id="addMedicineModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div id="addMedicineOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity opacity-0 duration-300" aria-hidden="true" onclick="closeAddMedicineModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div id="addMedicinePanel" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95 duration-300">
                <form id="add-medicine-form" onsubmit="handleInventoryAjax(event, this)">
                    <div class="bg-white px-6 pt-6 pb-6 border-b border-gray-100">
                        <div class="flex items-center justify-between mb-5">
                            <h3 class="text-xl font-bold text-gray-900">Add New Medicine</h3>
                            <button type="button" onclick="closeAddMedicineModal()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-6 w-6"></i></button>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Medicine Name</label>
                                <input type="text" name="name" required class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                                    <input type="text" name="category" placeholder="e.g. Painkiller" required class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Dosage</label>
                                    <input type="text" name="dosage" placeholder="e.g. 500mg" required class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity (pcs)</label>
                                    <input type="number" name="quantity" min="0" required class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Expiration Date</label>
                                    <input type="date" name="expiration_date" required class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-4 flex flex-col sm:flex-row-reverse gap-3">
                        <button type="submit" class="w-full sm:w-auto inline-flex justify-center rounded-xl shadow-sm px-6 py-2.5 bg-pup-maroon text-sm font-bold text-white hover:bg-pup-maroonDark transition-colors">Save Item</button>
                        <button type="button" onclick="closeAddMedicineModal()" class="w-full sm:w-auto inline-flex justify-center rounded-xl border border-gray-300 shadow-sm px-6 py-2.5 bg-white text-sm font-bold text-gray-700 hover:bg-gray-50 transition-colors">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Medicine Modal -->
    <div id="editMedModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div id="editMedOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity opacity-0 duration-300" onclick="closeEditMedModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div id="editMedPanel" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95 duration-300">
                <form id="edit-medicine-form" onsubmit="handleInventoryAjax(event, this)">
                    <input type="hidden" name="action" value="edit_medicine">
                    <input type="hidden" name="medicine_id" id="edit-med-id">
                    <div class="bg-white px-6 pt-6 pb-6 border-b border-gray-100">
                        <div class="flex items-center justify-between mb-5">
                            <h3 class="text-xl font-bold text-gray-900">Edit Medicine Details</h3>
                            <button type="button" onclick="closeEditMedModal()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-6 w-6"></i></button>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Medicine Name</label>
                                <input type="text" name="name" id="edit-med-name" required class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                                    <input type="text" name="category" id="edit-med-cat" required class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Dosage</label>
                                    <input type="text" name="dosage" id="edit-med-dos" required class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity (pcs)</label>
                                    <input type="number" name="quantity" id="edit-med-qty" min="0" required class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Expiration Date</label>
                                    <input type="date" name="expiration_date" id="edit-med-exp" required class="block w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-pup-maroon focus:border-pup-maroon sm:text-sm">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-4 flex flex-col sm:flex-row-reverse gap-3">
                        <button type="submit" class="w-full sm:w-auto inline-flex justify-center rounded-xl shadow-sm px-6 py-2.5 bg-pup-maroon text-sm font-bold text-white hover:bg-pup-maroonDark transition-colors">Save Changes</button>
                        <button type="button" onclick="closeEditMedModal()" class="w-full sm:w-auto inline-flex justify-center rounded-xl border border-gray-300 shadow-sm px-6 py-2.5 bg-white text-sm font-bold text-gray-700 hover:bg-gray-50 transition-colors">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteMedModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div id="deleteMedOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity opacity-0 duration-300" onclick="closeDeleteMedModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div id="deleteMedPanel" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-sm w-full opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95 duration-300">
                <form id="delete-medicine-form" onsubmit="handleInventoryAjax(event, this)">
                    <input type="hidden" name="action" value="delete_medicine">
                    <input type="hidden" name="medicine_id" id="delete-med-id">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10"><i data-lucide="alert-triangle" class="h-6 w-6 text-red-600"></i></div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-bold text-gray-900">Delete Medicine</h3>
                                <div class="mt-2"><p class="text-sm text-gray-500">Are you sure you want to permanently delete this item from the inventory? This action cannot be undone.</p></div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 flex flex-col sm:flex-row-reverse gap-2">
                        <button type="submit" class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-bold text-white hover:bg-red-700 sm:w-auto sm:text-sm transition-colors text-center">Delete</button>
                        <button type="button" onclick="closeDeleteMedModal()" class="w-full inline-flex justify-center rounded-xl border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-bold text-gray-700 hover:bg-gray-50 sm:w-auto sm:text-sm transition-colors">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div id="logoutModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div id="logoutModalOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity opacity-0 duration-300" aria-hidden="true" onclick="closeLogoutModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div id="logoutModalPanel" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-sm w-full opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95 duration-300">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10"><i data-lucide="log-out" class="h-6 w-6 text-red-600"></i></div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-bold text-gray-900" id="modal-title">Sign Out</h3>
                            <div class="mt-2"><p class="text-sm text-gray-500">Are you sure you want to sign out? You will need to log in again to access the admin portal.</p></div>
                        </div>
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

        // AJAX Handler for Real-Time Inventory Updates
        function handleInventoryAjax(e, form) {
            e.preventDefault();
            const formData = new FormData(form);
            formData.append('ajax', '1');
            
            fetch('medicine_inventory.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    // Show Error Modal
                    document.getElementById('alertModalIcon').outerHTML = '<i data-lucide="alert-circle" class="h-6 w-6 text-red-600" id="alertModalIcon"></i>';
                    document.getElementById('alertModalIconContainer').className = 'mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10';
                    document.getElementById('alertModalTitle').innerText = 'Action Failed';
                    document.getElementById('alertModalMessage').innerText = data.error;
                    document.getElementById('alertModalCloseBtn').className = 'w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-bold text-white hover:bg-red-700 sm:w-auto sm:text-sm transition-colors text-center';
                    openAlertModal();
                } else if (data.success) {
                    // Update table body and show Success Modal
                    document.getElementById('inventory-tbody').innerHTML = data.html;
                    if(form.id === 'add-medicine-form') form.reset();
                    
                    document.getElementById('alertModalIcon').outerHTML = '<i data-lucide="check-circle-2" class="h-6 w-6 text-green-600" id="alertModalIcon"></i>';
                    document.getElementById('alertModalIconContainer').className = 'mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100 sm:mx-0 sm:h-10 sm:w-10';
                    document.getElementById('alertModalTitle').innerText = 'Success';
                    document.getElementById('alertModalMessage').innerText = data.success;
                    document.getElementById('alertModalCloseBtn').className = 'w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-2 bg-gray-900 text-base font-bold text-white hover:bg-gray-800 sm:w-auto sm:text-sm transition-colors text-center';
                    openAlertModal();
                }

                // Re-initialize Lucide Icons for dynamically added elements
                lucide.createIcons();
                
                // Close Item Modals
                closeAddMedicineModal();
                closeEditMedModal();
                closeDeleteMedModal();
            })
            .catch(err => console.error("AJAX Error:", err));
        }

        // Generic Alert Modal Logic
        function openAlertModal() {
            const modal = document.getElementById('alertModal');
            modal.classList.remove('hidden');
            setTimeout(() => {
                document.getElementById('alertModalOverlay').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('alertModalPanel').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('alertModalPanel').classList.replace('translate-y-4', 'translate-y-0');
                document.getElementById('alertModalPanel').classList.replace('sm:scale-95', 'sm:scale-100');
            }, 10);
        }
        function closeAlertModal() {
            document.getElementById('alertModalOverlay').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('alertModalPanel').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('alertModalPanel').classList.replace('translate-y-0', 'translate-y-4');
            document.getElementById('alertModalPanel').classList.replace('sm:scale-100', 'sm:scale-95');
            setTimeout(() => document.getElementById('alertModal').classList.add('hidden'), 300);
        }

        // Add Medicine Modal
        function openAddMedicineModal() {
            const modal = document.getElementById('addMedicineModal');
            modal.classList.remove('hidden');
            setTimeout(() => {
                document.getElementById('addMedicineOverlay').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('addMedicinePanel').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('addMedicinePanel').classList.replace('translate-y-4', 'translate-y-0');
                document.getElementById('addMedicinePanel').classList.replace('sm:scale-95', 'sm:scale-100');
            }, 10);
        }
        function closeAddMedicineModal() {
            document.getElementById('addMedicineOverlay').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('addMedicinePanel').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('addMedicinePanel').classList.replace('translate-y-0', 'translate-y-4');
            document.getElementById('addMedicinePanel').classList.replace('sm:scale-100', 'sm:scale-95');
            setTimeout(() => document.getElementById('addMedicineModal').classList.add('hidden'), 300);
        }

        // Edit Medicine Modal
        function openEditMedModal(btn) {
            const data = JSON.parse(btn.getAttribute('data-med'));
            document.getElementById('edit-med-id').value = data.id;
            document.getElementById('edit-med-name').value = data.name || '';
            document.getElementById('edit-med-cat').value = data.category || '';
            document.getElementById('edit-med-dos').value = data.dosage || '';
            document.getElementById('edit-med-qty').value = data.quantity || 0;
            document.getElementById('edit-med-exp').value = data.expiration_date || '';

            const m = document.getElementById('editMedModal');
            m.classList.remove('hidden');
            setTimeout(() => {
                document.getElementById('editMedOverlay').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('editMedPanel').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('editMedPanel').classList.replace('translate-y-4', 'translate-y-0');
                document.getElementById('editMedPanel').classList.replace('sm:scale-95', 'sm:scale-100');
            }, 10);
        }
        function closeEditMedModal() {
            document.getElementById('editMedOverlay').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('editMedPanel').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('editMedPanel').classList.replace('translate-y-0', 'translate-y-4');
            document.getElementById('editMedPanel').classList.replace('sm:scale-100', 'sm:scale-95');
            setTimeout(() => document.getElementById('editMedModal').classList.add('hidden'), 300);
        }

        // Delete Medicine Modal
        function openDeleteMedModal(id) {
            document.getElementById('delete-med-id').value = id;
            const m = document.getElementById('deleteMedModal');
            m.classList.remove('hidden');
            setTimeout(() => {
                document.getElementById('deleteMedOverlay').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('deleteMedPanel').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('deleteMedPanel').classList.replace('translate-y-4', 'translate-y-0');
                document.getElementById('deleteMedPanel').classList.replace('sm:scale-95', 'sm:scale-100');
            }, 10);
        }
        function closeDeleteMedModal() {
            document.getElementById('deleteMedOverlay').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('deleteMedPanel').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('deleteMedPanel').classList.replace('translate-y-0', 'translate-y-4');
            document.getElementById('deleteMedPanel').classList.replace('sm:scale-100', 'sm:scale-95');
            setTimeout(() => document.getElementById('deleteMedModal').classList.add('hidden'), 300);
        }

        // Logout
        function openLogoutModal() {
            const modal = document.getElementById('logoutModal');
            modal.classList.remove('hidden');
            setTimeout(() => {
                document.getElementById('logoutModalOverlay').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('logoutModalPanel').classList.replace('opacity-0', 'opacity-100');
                document.getElementById('logoutModalPanel').classList.replace('translate-y-4', 'translate-y-0');
                document.getElementById('logoutModalPanel').classList.replace('sm:scale-95', 'sm:scale-100');
            }, 10);
        }
        function closeLogoutModal() {
            document.getElementById('logoutModalOverlay').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('logoutModalPanel').classList.replace('opacity-100', 'opacity-0');
            document.getElementById('logoutModalPanel').classList.replace('translate-y-0', 'translate-y-4');
            document.getElementById('logoutModalPanel').classList.replace('sm:scale-100', 'sm:scale-95');
            setTimeout(() => document.getElementById('logoutModal').classList.add('hidden'), 300);
        }
    </script>
</body>
</html>