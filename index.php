<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to MediLog</title>
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
    </style>
</head>
<body class="font-sans antialiased text-gray-800 bg-gray-50 flex flex-col min-h-screen relative overflow-x-hidden bg-[url('https://www.transparenttextures.com/patterns/cubes.png')]">

    <!-- Global Loader (Path adjusted for root directory) -->
    <?php include 'global_loader.php'; ?>

    <!-- Top Accent Line -->
    <div class="absolute top-0 w-full h-2 bg-pup-maroon z-50"></div>

    <!-- Navigation Bar -->
    <nav class="w-full px-6 md:px-12 py-6 flex justify-between items-center relative z-20">
        <div class="flex items-center gap-3">
            <div class="bg-pup-maroon p-2.5 rounded-xl shadow-md">
                <i data-lucide="clipboard-heart" class="h-6 w-6 text-pup-gold"></i>
            </div>
            <span class="font-extrabold text-2xl tracking-tight text-gray-900">MediLog</span>
        </div>
        <div class="hidden md:flex gap-8 font-semibold text-gray-500 text-sm">
            <a href="#" class="hover:text-pup-maroon transition-colors">About Clinic</a>
            <a href="#" class="hover:text-pup-maroon transition-colors">Services</a>
            <a href="#" class="hover:text-pup-maroon transition-colors">Contact</a>
        </div>
    </nav>

    <!-- Main Hero Section -->
    <main class="flex-1 flex items-center justify-center relative z-10 px-6 md:px-12 py-12 md:py-0">
        
        <!-- Background Ambient Decorations -->
        <div class="absolute top-20 right-10 w-72 h-72 bg-pup-gold/10 rounded-full blur-3xl -z-10"></div>
        <div class="absolute bottom-10 left-10 w-96 h-96 bg-pup-maroon/5 rounded-full blur-3xl -z-10"></div>

        <div class="w-full max-w-6xl grid grid-cols-1 md:grid-cols-2 gap-12 md:gap-8 items-center">
            
            <!-- Left Content Area -->
            <div class="space-y-8 text-center md:text-left">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-red-50 border border-red-100 text-pup-maroon text-xs font-bold uppercase tracking-wider mb-2 shadow-sm">
                    <span class="w-2 h-2 rounded-full bg-pup-maroon animate-pulse"></span>
                    University Clinic Portal
                </div>
                
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-extrabold text-gray-900 leading-tight">
                    Your Health,<br>
                    <span class="text-pup-maroon">Our Priority.</span>
                </h1>
                
                <p class="text-base md:text-lg text-gray-500 max-w-lg mx-auto md:mx-0 font-medium">
                    Streamline your campus healthcare experience. Book appointments, request medical clearances, and access your health records securely.
                </p>

                <div class="flex flex-col sm:flex-row gap-4 justify-center md:justify-start pt-4">
                    <a href="auth/studentlogin.php" class="flex items-center justify-center gap-3 py-4 px-8 rounded-xl shadow-lg shadow-red-900/20 text-base font-bold text-white bg-pup-maroon hover:bg-pup-maroonDark transition-all transform hover:-translate-y-1 focus:ring-4 focus:ring-red-100">
                        <i data-lucide="graduation-cap" class="h-5 w-5"></i> Student Portal
                    </a>
                    
                    <a href="auth/facultylogin.php" class="flex items-center justify-center gap-3 py-4 px-8 border-2 border-gray-200 rounded-xl text-base font-bold text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-300 transition-all transform hover:-translate-y-1">
                        <i data-lucide="shield-check" class="h-5 w-5 text-gray-500"></i> Clinic Admin
                    </a>
                </div>
            </div>

            <!-- Right Content Area (CSS Illustration) -->
            <div class="hidden md:flex justify-center relative">
                <div class="relative w-full max-w-[28rem] aspect-square bg-white rounded-full border-4 border-gray-50 shadow-2xl p-8 flex items-center justify-center z-10">
                    <!-- Spinning dashed ring -->
                    <div class="absolute inset-0 rounded-full border-2 border-dashed border-gray-200 animate-[spin_20s_linear_infinite]"></div>
                    
                    <!-- Floating Widget 1 -->
                    <div class="absolute -left-6 top-24 bg-white p-4 rounded-2xl shadow-xl border border-gray-100 flex items-center gap-3 animate-[bounce_3s_ease-in-out_infinite]">
                        <div class="bg-green-100 p-2.5 rounded-full text-green-600"><i data-lucide="check-circle-2" class="h-5 w-5"></i></div>
                        <div class="text-left">
                            <p class="text-xs font-bold text-gray-900">Clearance</p>
                            <p class="text-[10px] text-gray-500 font-medium">Approved Today</p>
                        </div>
                    </div>

                    <!-- Floating Widget 2 -->
                    <div class="absolute -right-8 bottom-32 bg-white p-4 rounded-2xl shadow-xl border border-gray-100 flex items-center gap-3 animate-[bounce_4s_ease-in-out_infinite]">
                        <div class="bg-blue-100 p-2.5 rounded-full text-blue-600"><i data-lucide="calendar" class="h-5 w-5"></i></div>
                        <div class="text-left">
                            <p class="text-xs font-bold text-gray-900">Appointment</p>
                            <p class="text-[10px] text-gray-500 font-medium">Tomorrow, 10 AM</p>
                        </div>
                    </div>

                    <!-- Center Heart Graphic -->
                    <div class="bg-red-50 w-64 h-64 rounded-full flex flex-col items-center justify-center text-pup-maroon shadow-inner">
                        <i data-lucide="heart-pulse" class="h-24 w-24 mb-4 drop-shadow-md"></i>
                        <p class="font-extrabold text-2xl tracking-widest uppercase">MediLog</p>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-auto relative z-20">
        <div class="max-w-6xl mx-auto px-6 md:px-12 py-8">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="flex items-center gap-2 text-gray-900 font-extrabold text-lg">
                    <i data-lucide="clipboard-heart" class="h-5 w-5 text-pup-maroon"></i> MediLog
                </div>
                <p class="text-sm text-gray-500 font-medium text-center">
                    &copy; <?php echo date("Y"); ?> Polytechnic University of the Philippines. All rights reserved.
                </p>
                <div class="flex gap-5">
                    <a href="#" class="text-gray-400 hover:text-pup-maroon transition-colors"><span class="sr-only">Facebook</span><i data-lucide="facebook" class="h-5 w-5"></i></a>
                    <a href="#" class="text-gray-400 hover:text-pup-maroon transition-colors"><span class="sr-only">Twitter</span><i data-lucide="twitter" class="h-5 w-5"></i></a>
                    <a href="#" class="text-gray-400 hover:text-pup-maroon transition-colors"><span class="sr-only">Website</span><i data-lucide="globe" class="h-5 w-5"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>