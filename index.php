<?php
session_start();

// If the user is already logged in, redirect them to their respective dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'faculty') {
        header("Location: admin_dashboard.php");
        exit();
    } elseif ($_SESSION['role'] === 'student') {
        header("Location: student_dashboard.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Role - MediLog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'], }, colors: { pup: { maroon: '#880000', maroonDark: '#660000', gold: '#F1B500', goldLight: '#FDE68A', } }, animation: { blob: "blob 7s infinite", }, keyframes: { blob: { "0%": { transform: "translate(0px, 0px) scale(1)" }, "33%": { transform: "translate(30px, -50px) scale(1.1)" }, "66%": { transform: "translate(-20px, 20px) scale(0.9)" }, "100%": { transform: "translate(0px, 0px) scale(1)" } } } } } }
    </script>
    <style>
        body { opacity: 0; animation: fadeIn 0.4s ease-out forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .page-exit { animation: fadeOut 0.3s ease-in forwards !important; }
        @keyframes fadeOut { from { opacity: 1; transform: translateY(0); } to { opacity: 0; transform: translateY(-10px); } }
        .glass-card { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.5); }
        .animation-delay-2000 { animation-delay: 2s; }
        .animation-delay-4000 { animation-delay: 4s; }
    </style>
</head>
<body class="font-sans antialiased text-gray-800 bg-gray-50 min-h-screen flex flex-col relative overflow-x-hidden">

    <!-- Animated Background Elements -->
    <div class="fixed inset-0 w-full h-full z-0 overflow-hidden pointer-events-none">
        <div class="absolute top-0 left-1/4 w-96 h-96 bg-pup-maroon rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob"></div>
        <div class="absolute top-0 right-1/4 w-96 h-96 bg-pup-gold rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob animation-delay-2000"></div>
        <div class="absolute -bottom-32 left-1/3 w-96 h-96 bg-red-300 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-4000"></div>
    </div>

    <!-- Main Content Area -->
    <main class="flex-grow flex flex-col items-center justify-center p-4 sm:p-6 lg:p-8 w-full relative z-10">
        <div class="w-full max-w-5xl mx-auto">
            
            <!-- Header Section -->
            <div class="text-center mb-12 mt-8">
                <div class="inline-flex items-center justify-center bg-white p-4 rounded-3xl mb-6 shadow-lg border border-gray-100 transform hover:scale-105 transition-transform">
                    <i data-lucide="clipboard-heart" class="h-12 w-12 text-pup-maroon"></i>
                </div>
                <h1 class="text-4xl sm:text-5xl font-extrabold text-gray-900 mb-4 tracking-tight">Welcome to <span class="text-transparent bg-clip-text bg-gradient-to-r from-pup-maroon to-pup-gold">MediLog</span></h1>
                <p class="text-lg sm:text-xl text-gray-600 font-medium">Select your portal access to continue securely.</p>
            </div>

            <!-- Role Selection Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 lg:gap-10 mb-12 px-4 sm:px-0">
                
                <!-- Student Option -->
                <a href="auth/studentlogin.php" class="glass-card rounded-3xl p-8 sm:p-10 hover:shadow-2xl hover:border-pup-maroon/50 transition-all duration-500 relative overflow-hidden flex flex-col items-center text-center group transform hover:-translate-y-2">
                    <div class="absolute inset-0 bg-gradient-to-br from-pup-maroon/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="w-24 h-24 bg-red-50 text-pup-maroon rounded-2xl flex items-center justify-center mb-8 group-hover:bg-pup-maroon group-hover:text-white transition-all duration-500 shadow-sm group-hover:shadow-md transform group-hover:rotate-3">
                        <i data-lucide="graduation-cap" class="h-12 w-12"></i>
                    </div>
                    
                    <h2 class="text-3xl font-extrabold text-gray-900 mb-4 group-hover:text-pup-maroon transition-colors">Iskolar ng Bayan</h2>
                    <p class="text-gray-600 mb-10 leading-relaxed text-base">Access your student health records, book medical and dental appointments, and request official clearances.</p>
                    
                    <div class="mt-auto w-full py-4 px-6 rounded-2xl bg-white border border-gray-200 text-gray-800 font-bold group-hover:bg-pup-maroon group-hover:border-pup-maroon group-hover:text-white transition-all duration-300 flex items-center justify-center shadow-sm">
                        Student Portal <i data-lucide="arrow-right" class="ml-2 h-5 w-5 group-hover:translate-x-2 transition-transform"></i>
                    </div>
                </a>

                <!-- Faculty / Staff Option -->
                <a href="auth/facultylogin.php" class="glass-card rounded-3xl p-8 sm:p-10 hover:shadow-2xl hover:border-gray-900/30 transition-all duration-500 relative overflow-hidden flex flex-col items-center text-center group transform hover:-translate-y-2">
                    <div class="absolute inset-0 bg-gradient-to-br from-gray-900/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    
                    <div class="w-24 h-24 bg-gray-100 text-gray-800 rounded-2xl flex items-center justify-center mb-8 group-hover:bg-gray-900 group-hover:text-pup-gold transition-all duration-500 shadow-sm group-hover:shadow-md transform group-hover:-rotate-3">
                        <i data-lucide="briefcase-medical" class="h-12 w-12"></i>
                    </div>
                    
                    <h2 class="text-3xl font-extrabold text-gray-900 mb-4 group-hover:text-gray-900 transition-colors">Faculty & Admin</h2>
                    <p class="text-gray-600 mb-10 leading-relaxed text-base">Manage patient consultations, update medicine inventory, and review clearance requests securely.</p>
                    
                    <div class="mt-auto w-full py-4 px-6 rounded-2xl bg-white border border-gray-200 text-gray-800 font-bold group-hover:bg-gray-900 group-hover:border-gray-900 group-hover:text-pup-gold transition-all duration-300 flex items-center justify-center shadow-sm">
                        Employee Portal <i data-lucide="arrow-right" class="ml-2 h-5 w-5 group-hover:translate-x-2 transition-transform"></i>
                    </div>
                </a>

            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer class="relative z-10 w-full py-6 text-center text-gray-500 text-sm">
        <p>&copy; <?php echo date("Y"); ?> MediLog - Polytechnic University of the Philippines.</p>
    </footer>

    <script>
        lucide.createIcons();
        document.addEventListener('DOMContentLoaded', () => {
            const links = document.querySelectorAll('a[href]');
            links.forEach(link => {
                link.addEventListener('click', e => {
                    const href = link.getAttribute('href');
                    if (!href || href.startsWith('#') || link.getAttribute('target') === '_blank') return;
                    e.preventDefault();
                    document.body.classList.add('page-exit');
                    setTimeout(() => { window.location.href = href; }, 250); 
                });
            });
        });
    </script>
</body>
</html>