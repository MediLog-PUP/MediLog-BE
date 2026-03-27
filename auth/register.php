<?php
require '../db_connect.php';

$success_msg = '';
$error_msg = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = $_POST['full-name'];
    $id_number = $_POST['id-number'];
    $role = $_POST['role'];
    $email = $_POST['email'];
    
    // Only capture course and section if the role is student
    $course = ($role === 'student') ? ($_POST['course'] ?? null) : null;
    $section = ($role === 'student') ? ($_POST['section'] ?? null) : null;
    
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm-password'];

    if ($password !== $confirm_password) {
        $error_msg = "Passwords do not match!";
    } else {
        // Hash the password for security
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("INSERT INTO users (full_name, id_number, role, email, course, section, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$full_name, $id_number, $role, $email, $course, $section, $hashed_password]);
            $success_msg = "Registration successful! You can now login.";
        } catch(PDOException $e) {
            if($e->errorInfo[1] == 1062) {
                $error_msg = "That ID Number or Email is already registered.";
            } else {
                $error_msg = "Something went wrong. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MediLog</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { pup: { maroon: '#880000', maroonDark: '#660000', gold: '#F1B500', goldLight: '#FDE68A' } },
                    animation: { blob: "blob 7s infinite" },
                    keyframes: {
                        blob: {
                            "0%": { transform: "translate(0px, 0px) scale(1)" },
                            "33%": { transform: "translate(30px, -50px) scale(1.1)" },
                            "66%": { transform: "translate(-20px, 20px) scale(0.9)" },
                            "100%": { transform: "translate(0px, 0px) scale(1)" }
                        }
                    }
                }
            }
        }
    </script>

    <style>
        body { opacity: 0; animation: fadeIn 0.4s ease-out forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .page-exit { animation: fadeOut 0.3s ease-in forwards !important; }
        @keyframes fadeOut { from { opacity: 1; transform: translateY(0); } to { opacity: 0; transform: translateY(-10px); } }
        
        .glass-panel { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); }
        .animation-delay-2000 { animation-delay: 2s; }
        
        /* Modal Scroll styling */
        .modal-scroll::-webkit-scrollbar { width: 6px; }
        .modal-scroll::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 8px; }
        .modal-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 8px; }
        .modal-scroll::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
    </style>
</head>
<body class="font-sans antialiased text-gray-800 min-h-screen relative flex items-center justify-center bg-gray-50 overflow-x-hidden py-8">

    <!-- Decorative Background Elements -->
    <div class="fixed top-[-10%] left-[-10%] w-96 h-96 bg-pup-maroon rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob pointer-events-none"></div>
    <div class="fixed bottom-[-10%] right-[-10%] w-96 h-96 bg-pup-gold rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-2000 pointer-events-none"></div>

    <div class="w-full max-w-6xl mx-auto px-4 sm:px-6 relative z-10 flex flex-col lg:flex-row items-stretch min-h-[700px] shadow-2xl rounded-3xl overflow-hidden bg-white/50 border border-white/40">
        
        <!-- Left Branding Panel -->
        <div class="w-full lg:w-5/12 bg-pup-maroon p-8 sm:p-12 flex flex-col justify-between relative overflow-hidden rounded-t-3xl lg:rounded-l-3xl lg:rounded-tr-none">
            <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80')] bg-cover bg-center opacity-10 mix-blend-overlay"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-pup-maroonDark/90 to-pup-maroon/60"></div>
            
            <div class="relative z-10 flex items-center gap-3 text-white mb-8">
                <div class="bg-white/20 p-2 rounded-xl backdrop-blur-sm border border-white/30">
                    <i data-lucide="clipboard-heart" class="h-6 w-6 text-pup-gold"></i>
                </div>
                <span class="text-xl font-bold tracking-wider uppercase">MediLog</span>
            </div>

            <div class="relative z-10 mt-8 lg:mt-0 py-8">
                <h2 class="text-3xl sm:text-4xl font-extrabold text-white mb-4 leading-tight">Your Health, <br><span class="text-pup-gold">Our Priority.</span></h2>
                <p class="text-white/80 text-base sm:text-lg mb-8 leading-relaxed">Register for your secure portal to easily book appointments, track health records, and request medical clearances.</p>
            </div>
            
            <div class="relative z-10 hidden lg:flex items-center gap-3 text-white/70 text-sm">
                <i data-lucide="shield-check" class="h-5 w-5 text-pup-gold"></i> Secure & Confidential Health System
            </div>
        </div>

        <!-- Right Form Panel -->
        <div class="w-full lg:w-7/12 glass-panel p-6 sm:p-10 flex flex-col justify-center rounded-b-3xl lg:rounded-r-3xl lg:rounded-bl-none overflow-y-auto">
            
            <a href="../index.php" class="inline-flex items-center text-sm font-semibold text-gray-500 hover:text-pup-maroon transition-colors mb-6 w-max group">
                <i data-lucide="arrow-left" class="mr-2 h-4 w-4 transform group-hover:-translate-x-1 transition-transform"></i> Back
            </a>

            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Create Account</h1>
                <p class="text-gray-500">Fill in your details to create your secure portal account.</p>
            </div>

            <?php if($error_msg): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 flex items-start gap-3 text-sm font-medium animate-bounce">
                    <i data-lucide="alert-circle" class="h-5 w-5 flex-shrink-0 mt-0.5"></i>
                    <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <?php if($success_msg): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6 flex items-start gap-3 text-sm font-medium">
                    <i data-lucide="check-circle-2" class="h-5 w-5 flex-shrink-0 mt-0.5"></i>
                    <div>
                        <?php echo htmlspecialchars($success_msg); ?>
                        <a href="studentlogin.php" class="underline font-bold hover:text-green-900 ml-1">Login here.</a>
                    </div>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST" class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Full Name</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i data-lucide="user" class="h-5 w-5 text-gray-400"></i>
                        </div>
                        <input type="text" name="full-name" class="block w-full pl-11 pr-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-pup-maroon focus:border-transparent sm:text-sm transition-all bg-gray-50 focus:bg-white" placeholder="Juan Dela Cruz" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">ID Number</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i data-lucide="id-card" class="h-5 w-5 text-gray-400"></i>
                            </div>
                            <input type="text" name="id-number" class="block w-full pl-11 pr-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-pup-maroon focus:border-transparent sm:text-sm transition-all bg-gray-50 focus:bg-white" placeholder="2021-00000-MN-0" required>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Role</label>
                        <select name="role" id="role-select" onchange="toggleCourseSection()" class="block w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-pup-maroon focus:border-transparent sm:text-sm transition-all bg-gray-50 focus:bg-white text-gray-700" required>
                            <option value="student">Student</option>
                            <option value="faculty">Faculty / Staff</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">University Email</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i data-lucide="mail" class="h-5 w-5 text-gray-400"></i>
                        </div>
                        <input type="email" name="email" class="block w-full pl-11 pr-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-pup-maroon focus:border-transparent sm:text-sm transition-all bg-gray-50 focus:bg-white" placeholder="name@iskolarngbayan.pup.edu.ph" required>
                    </div>
                </div>

                <!-- Course & Section (Dynamically Hidden for Faculty) -->
                <div id="student-fields" class="grid grid-cols-1 sm:grid-cols-2 gap-5 transition-all duration-300">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Course</label>
                        <select name="course" id="course-select" class="block w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-pup-maroon focus:border-transparent sm:text-sm transition-all bg-gray-50 focus:bg-white text-gray-700">
                            <option value="" disabled selected>Select Course</option>
                            <option value="BS Information Technology">BS Information Technology</option>
                            <option value="Education">Education</option>
                            <option value="Industrial Engineering">Industrial Engineering</option>
                            <option value="Public Administration">Public Administration</option>
                            <option value="Electrical Engineering">Electrical Engineering</option>
                            <option value="Diploma in Information Technology">Diploma in Information Technology</option>
                            <option value="Diploma in Office Management Technology">Diploma in Office Management Technology</option>
                            <option value="Entrepreneurship">Entrepreneurship</option>
                            <option value="Psychology">Psychology</option>
                            <option value="Electronics Engineering">Electronics Engineering</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Section</label>
                        <input type="text" name="section" id="section-input" placeholder="e.g. 3-1" class="block w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-pup-maroon focus:border-transparent sm:text-sm transition-all bg-gray-50 focus:bg-white">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i data-lucide="lock" class="h-5 w-5 text-gray-400"></i>
                            </div>
                            <input type="password" name="password" class="block w-full pl-11 pr-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-pup-maroon focus:border-transparent sm:text-sm transition-all bg-gray-50 focus:bg-white" placeholder="••••••••" required>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirm Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i data-lucide="shield-check" class="h-5 w-5 text-gray-400"></i>
                            </div>
                            <input type="password" name="confirm-password" class="block w-full pl-11 pr-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-pup-maroon focus:border-transparent sm:text-sm transition-all bg-gray-50 focus:bg-white" placeholder="••••••••" required>
                        </div>
                    </div>
                </div>

                <div class="flex items-start mt-2">
                    <div class="flex items-center h-5">
                        <input id="terms" name="terms" type="checkbox" class="h-4 w-4 text-pup-maroon focus:ring-pup-maroon border-gray-300 rounded cursor-pointer" required>
                    </div>
                    <label for="terms" class="ml-2 block text-sm text-gray-700">
                        I agree to the <button type="button" onclick="openTermsModal()" class="text-pup-maroon font-semibold hover:text-pup-maroonDark hover:underline focus:outline-none transition-colors">Terms and Conditions</button>
                    </label>
                </div>

                <button type="submit" class="w-full flex justify-center items-center py-3.5 px-4 rounded-xl shadow-md text-sm font-bold text-white bg-pup-maroon hover:bg-pup-maroonDark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-pup-maroon transition-all transform hover:-translate-y-0.5 mt-6">
                    Create Account <i data-lucide="arrow-right" class="ml-2 h-4 w-4"></i>
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-gray-100 text-center text-sm text-gray-500">
                Already have an account? <a href="studentlogin.php" class="font-bold text-pup-maroon hover:text-pup-maroonDark transition-colors">Sign in instead</a>
            </div>
        </div>
    </div>

    <!-- Terms and Conditions Modal -->
    <div id="termsModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div id="modalOverlay" class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity opacity-0 duration-300" aria-hidden="true" onclick="closeTermsModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            
            <div id="modalPanel" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95 duration-300">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-50 sm:mx-0 sm:h-10 sm:w-10">
                            <i data-lucide="file-text" class="h-5 w-5 text-pup-maroon"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-bold text-gray-900" id="modal-title">Terms and Conditions</h3>
                            <div class="mt-3 bg-gray-50 rounded-lg p-4 h-48 overflow-y-auto modal-scroll border border-gray-100">
                                <p class="text-sm text-gray-600 space-y-3">
                                    <span class="block">Welcome to MediLog. By accessing or using this portal, you agree to be bound by the following policies:</span>
                                    <strong class="block text-gray-800 mt-2">1. Privacy & Confidentiality</strong>
                                    <span class="block">All student medical records and personal data are kept strictly confidential in compliance with the Data Privacy Act of 2012 (R.A. 10173).</span>
                                    <strong class="block text-gray-800 mt-2">2. Accuracy of Information</strong>
                                    <span class="block">You agree to provide true, accurate, and complete information regarding your health status and medical logs submitted to the clinic.</span>
                                    <strong class="block text-gray-800 mt-2">3. Authenticity, Tracking, and Security</strong>
                                    <span class="block">You are strictly responsible for maintaining the confidentiality of your credentials. To ensure security and authenticity, the system actively traces actions and identifies responsible personnel or users for modifications to medical records.</span>
                                    <strong class="block text-gray-800 mt-2">4. Official Usage Only</strong>
                                    <span class="block">This portal is meant exclusively for official university medical logging, inquiries, and scheduling. Misuse may result in disciplinary action.</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-gray-100">
                    <button type="button" onclick="closeTermsModal()" class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-5 py-2.5 bg-pup-maroon text-base font-medium text-white hover:bg-pup-maroonDark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-pup-maroon sm:ml-3 sm:w-auto sm:text-sm transition-colors">
                        I Understand
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function toggleCourseSection() {
            const role = document.getElementById('role-select').value;
            const studentFields = document.getElementById('student-fields');
            const courseSelect = document.getElementById('course-select');
            const sectionInput = document.getElementById('section-input');

            if (role === 'student') {
                studentFields.style.display = 'grid';
                courseSelect.setAttribute('required', 'required');
                sectionInput.setAttribute('required', 'required');
            } else {
                studentFields.style.display = 'none';
                courseSelect.removeAttribute('required');
                sectionInput.removeAttribute('required');
                courseSelect.value = '';
                sectionInput.value = '';
            }
        }

        document.addEventListener('DOMContentLoaded', toggleCourseSection);

        const modal = document.getElementById('termsModal');
        const modalOverlay = document.getElementById('modalOverlay');
        const modalPanel = document.getElementById('modalPanel');

        function openTermsModal() {
            modal.classList.remove('hidden');
            void modal.offsetWidth;
            modalOverlay.classList.remove('opacity-0');
            modalOverlay.classList.add('opacity-100');
            modalPanel.classList.remove('opacity-0', 'translate-y-4', 'sm:scale-95');
            modalPanel.classList.add('opacity-100', 'translate-y-0', 'sm:scale-100');
        }

        function closeTermsModal() {
            modalOverlay.classList.remove('opacity-100');
            modalOverlay.classList.add('opacity-0');
            modalPanel.classList.remove('opacity-100', 'translate-y-0', 'sm:scale-100');
            modalPanel.classList.add('opacity-0', 'translate-y-4', 'sm:scale-95');
            
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const links = document.querySelectorAll('a[href]:not([href^="#"]):not([target="_blank"])');
            links.forEach(link => {
                link.addEventListener('click', e => {
                    const href = link.getAttribute('href');
                    if(href === "javascript:void(0);" || href === "") return;
                    e.preventDefault();
                    document.body.classList.add('page-exit');
                    setTimeout(() => {
                        window.location.href = href;
                    }, 250); 
                });
            });
        });
    </script>
</body>
</html>