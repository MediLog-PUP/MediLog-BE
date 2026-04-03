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
        tailwind.config = { 
            theme: { 
                extend: { 
                    fontFamily: { sans: ['Inter', 'sans-serif'] }, 
                    colors: { pup: { maroon: '#880000', maroonDark: '#660000', gold: '#F1B500', goldLight: '#FDE68A' } },
                    animation: {
                        'blob': 'blob 7s infinite',
                        'float': 'float 6s ease-in-out infinite',
                        'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    },
                    keyframes: {
                        blob: {
                            '0%': { transform: 'translate(0px, 0px) scale(1)' },
                            '33%': { transform: 'translate(30px, -50px) scale(1.1)' },
                            '66%': { transform: 'translate(-20px, 20px) scale(0.9)' },
                            '100%': { transform: 'translate(0px, 0px) scale(1)' },
                        },
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-20px)' },
                        }
                    }
                } 
            } 
        }
    </script>
    <style>
        /* Smooth Page Transitions */
        body { opacity: 0; animation: fadeIn 0.6s ease-out forwards; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .page-exit { animation: fadeOut 0.4s ease-in forwards !important; }
        @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }
        
        /* Staggered Slide Up Animations */
        .slide-up { opacity: 0; transform: translateY(30px); animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .delay-100 { animation-delay: 100ms; }
        .delay-200 { animation-delay: 200ms; }
        .delay-300 { animation-delay: 300ms; }
        .delay-400 { animation-delay: 400ms; }
        @keyframes slideUp { to { opacity: 1; transform: translateY(0); } }
        
        .animation-delay-2000 { animation-delay: 2s; }
        .animation-delay-4000 { animation-delay: 4s; }
    </style>
</head>
<body class="font-sans antialiased text-gray-800 bg-slate-50 flex flex-col min-h-screen relative overflow-x-hidden selection:bg-pup-maroon selection:text-white">

    <!-- Global Loader -->
    <?php include 'global_loader.php'; ?>

    <!-- Animated Background Blobs -->
    <div class="fixed inset-0 w-full h-full z-0 overflow-hidden pointer-events-none">
        <div class="absolute top-[-10%] left-[-10%] w-96 h-96 bg-red-200 rounded-full mix-blend-multiply filter blur-3xl opacity-60 animate-blob"></div>
        <div class="absolute top-[20%] right-[-5%] w-96 h-96 bg-yellow-200 rounded-full mix-blend-multiply filter blur-3xl opacity-60 animate-blob animation-delay-2000"></div>
        <div class="absolute bottom-[-20%] left-[20%] w-[30rem] h-[30rem] bg-rose-200 rounded-full mix-blend-multiply filter blur-3xl opacity-60 animate-blob animation-delay-4000"></div>
    </div>

    <!-- Top Accent Line -->
    <div class="fixed top-0 w-full h-1.5 bg-gradient-to-r from-pup-maroon via-red-600 to-pup-gold z-50"></div>

    <!-- Navigation Bar (Glassmorphism) -->
    <nav class="w-full px-6 md:px-12 py-5 sticky top-0 z-40 bg-white/70 backdrop-blur-lg border-b border-white/50 shadow-sm transition-all duration-300">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3 slide-up">
                <div class="bg-gradient-to-br from-pup-maroon to-red-700 p-2.5 rounded-xl shadow-lg shadow-red-900/20">
                    <i data-lucide="clipboard-heart" class="h-6 w-6 text-white"></i>
                </div>
                <span class="font-extrabold text-2xl tracking-tight text-gray-900">MediLog</span>
            </div>
            <!-- Mobile Menu Icon -->
            <div class="md:hidden text-gray-600 hover:text-pup-maroon cursor-pointer slide-up">
                <i data-lucide="menu" class="h-7 w-7"></i>
            </div>
            <!-- Desktop Links -->
            <div class="hidden md:flex gap-10 font-semibold text-gray-500 text-sm slide-up delay-100">
                <a href="#" class="hover:text-pup-maroon transition-colors relative group">
                    About Clinic
                    <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-pup-maroon transition-all group-hover:w-full"></span>
                </a>
                <a href="#" class="hover:text-pup-maroon transition-colors relative group">
                    Services
                    <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-pup-maroon transition-all group-hover:w-full"></span>
                </a>
                <a href="#" class="hover:text-pup-maroon transition-colors relative group">
                    Contact
                    <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-pup-maroon transition-all group-hover:w-full"></span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Hero Section -->
    <main class="flex-1 flex items-center justify-center relative z-10 px-4 sm:px-6 md:px-12 py-12 lg:py-0 w-full max-w-7xl mx-auto">
        
        <div class="w-full grid grid-cols-1 lg:grid-cols-2 gap-16 lg:gap-8 items-center">
            
            <!-- Left Content Area -->
            <div class="space-y-8 text-center lg:text-left flex flex-col items-center lg:items-start z-20">
                
                <div class="slide-up inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/80 backdrop-blur-sm border border-red-100 text-pup-maroon text-xs font-bold uppercase tracking-wider shadow-sm">
                    <span class="relative flex h-2 w-2">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-2 w-2 bg-pup-maroon"></span>
                    </span>
                    University Clinic Portal
                </div>
                
                <h1 class="slide-up delay-100 text-5xl sm:text-6xl lg:text-7xl font-extrabold text-gray-900 leading-[1.1] tracking-tight">
                    Your Health,<br>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-pup-maroon to-red-500">Our Priority.</span>
                </h1>
                
                <p class="slide-up delay-200 text-base sm:text-lg text-gray-600 max-w-lg font-medium leading-relaxed px-4 lg:px-0">
                    Experience seamless campus healthcare. Book appointments, request medical clearances, and access your health records securely from anywhere.
                </p>

                <div class="slide-up delay-300 flex flex-col sm:flex-row gap-4 w-full sm:w-auto px-4 lg:px-0 pt-4">
                    <a href="auth/studentlogin.php" class="group flex items-center justify-center gap-3 py-4 px-8 rounded-2xl shadow-[0_8px_30px_rgb(136,0,0,0.2)] text-base font-bold text-white bg-gradient-to-r from-pup-maroon to-red-700 hover:from-red-800 hover:to-pup-maroon transition-all transform hover:-translate-y-1 w-full sm:w-auto">
                        <i data-lucide="graduation-cap" class="h-5 w-5 group-hover:scale-110 transition-transform"></i> Student Portal
                    </a>
                    
                    <a href="auth/facultylogin.php" class="group flex items-center justify-center gap-3 py-4 px-8 border border-gray-200/80 bg-white/60 backdrop-blur-md rounded-2xl shadow-sm text-base font-bold text-gray-700 hover:bg-white hover:border-gray-300 hover:shadow-md transition-all transform hover:-translate-y-1 w-full sm:w-auto">
                        <i data-lucide="shield-check" class="h-5 w-5 text-pup-maroon group-hover:scale-110 transition-transform"></i> Clinic Admin
                    </a>
                </div>
            </div>

            <!-- Right Content Area (Dynamic Floating Illustration) -->
            <div class="hidden sm:flex justify-center relative lg:justify-end slide-up delay-400 z-10 perspective-1000">
                <div class="relative w-full max-w-[22rem] lg:max-w-[26rem] aspect-[4/5] animate-float">
                    
                    <!-- Main Glass Card -->
                    <div class="absolute inset-0 bg-white/40 backdrop-blur-xl border border-white/60 rounded-[2rem] shadow-[0_20px_50px_rgba(0,0,0,0.1)] overflow-hidden flex flex-col p-8">
                        <div class="flex justify-between items-center mb-8">
                            <div class="w-12 h-12 bg-white rounded-2xl flex items-center justify-center shadow-sm text-pup-maroon">
                                <i data-lucide="activity" class="h-7 w-7"></i>
                            </div>
                            <div class="px-3 py-1 bg-green-100/80 text-green-700 text-xs font-bold rounded-full flex items-center gap-1.5">
                                <div class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></div> Active
                            </div>
                        </div>
                        
                        <div class="space-y-4 mb-auto">
                            <div class="h-3 w-3/4 bg-gray-200/80 rounded-full"></div>
                            <div class="h-3 w-1/2 bg-gray-200/80 rounded-full"></div>
                            <div class="h-3 w-5/6 bg-gray-200/80 rounded-full"></div>
                        </div>

                        <div class="mt-auto bg-gradient-to-r from-red-50 to-rose-50 p-5 rounded-2xl border border-red-100/50">
                            <div class="flex items-center gap-3">
                                <i data-lucide="heart-pulse" class="h-8 w-8 text-pup-maroon animate-pulse-slow"></i>
                                <div>
                                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">System Status</p>
                                    <p class="text-lg font-extrabold text-gray-900">All Systems Nominal</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Floating Widget 1 (Clearance) -->
                    <div class="absolute -left-8 top-20 bg-white/90 backdrop-blur-md p-4 rounded-2xl shadow-xl border border-white flex items-center gap-3 animate-[float_5s_ease-in-out_infinite_reverse]">
                        <div class="bg-green-100 p-2.5 rounded-full text-green-600"><i data-lucide="file-check-2" class="h-5 w-5"></i></div>
                        <div class="text-left pr-2">
                            <p class="text-sm font-bold text-gray-900 leading-tight">Clearance</p>
                            <p class="text-[10px] text-gray-500 font-medium">Approved Today</p>
                        </div>
                    </div>

                    <!-- Floating Widget 2 (Appointment) -->
                    <div class="absolute -right-10 bottom-24 bg-white/90 backdrop-blur-md p-4 rounded-2xl shadow-xl border border-white flex items-center gap-3 animate-[float_7s_ease-in-out_infinite]">
                        <div class="bg-blue-100 p-2.5 rounded-full text-blue-600"><i data-lucide="calendar-clock" class="h-5 w-5"></i></div>
                        <div class="text-left pr-2">
                            <p class="text-sm font-bold text-gray-900 leading-tight">Appointment</p>
                            <p class="text-[10px] text-gray-500 font-medium">Tomorrow, 10:00 AM</p>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </main>

    <!-- Centered & Enhanced Footer -->
    <footer class="w-full bg-white/40 backdrop-blur-xl border-t border-white/60 mt-auto relative z-20 slide-up delay-400 overflow-hidden">
        
        <!-- Decorative Top Glow -->
        <div class="absolute top-0 left-0 w-full h-[1px] bg-gradient-to-r from-transparent via-pup-maroon/40 to-transparent"></div>

        <div class="max-w-4xl mx-auto px-6 py-12 flex flex-col items-center justify-center text-center gap-10">
            
            <!-- Brand Section -->
            <div class="flex flex-col items-center gap-4">
                <div class="flex items-center gap-2 text-gray-900 font-extrabold text-2xl tracking-tight group cursor-default">
                    <div class="bg-white/80 p-2.5 rounded-xl shadow-sm border border-white group-hover:scale-110 group-hover:rotate-3 transition-all duration-300">
                        <i data-lucide="clipboard-heart" class="h-6 w-6 text-pup-maroon"></i>
                    </div>
                    MediLog
                </div>
                <p class="text-sm text-gray-500 font-medium max-w-md leading-relaxed">
                    Streamlining campus healthcare for the Polytechnic University of the Philippines. Your health is our top priority.
                </p>
            </div>

            <!-- Animated Links -->
            <div class="flex flex-wrap justify-center gap-6 sm:gap-10 text-sm font-bold text-gray-600">
                <a href="#" class="hover:text-pup-maroon transition-colors relative group py-1">
                    Privacy Policy
                    <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-pup-maroon transition-all duration-300 ease-out group-hover:w-full"></span>
                </a>
                <a href="#" class="hover:text-pup-maroon transition-colors relative group py-1">
                    Terms of Service
                    <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-pup-maroon transition-all duration-300 ease-out group-hover:w-full"></span>
                </a>
                <a href="#" class="hover:text-pup-maroon transition-colors relative group py-1">
                    Help Center
                    <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-pup-maroon transition-all duration-300 ease-out group-hover:w-full"></span>
                </a>
            </div>

            <!-- Elevated Social Icons -->
            <div class="flex justify-center gap-5">
                <a href="#" class="w-12 h-12 rounded-full bg-white/80 border border-gray-100 hover:bg-pup-maroon hover:border-pup-maroon hover:text-white flex items-center justify-center text-gray-500 transition-all duration-300 shadow-sm hover:shadow-lg hover:shadow-pup-maroon/30 hover:-translate-y-1.5">
                    <span class="sr-only">Facebook</span><i data-lucide="facebook" class="h-5 w-5"></i>
                </a>
                <a href="#" class="w-12 h-12 rounded-full bg-white/80 border border-gray-100 hover:bg-pup-maroon hover:border-pup-maroon hover:text-white flex items-center justify-center text-gray-500 transition-all duration-300 shadow-sm hover:shadow-lg hover:shadow-pup-maroon/30 hover:-translate-y-1.5">
                    <span class="sr-only">Twitter</span><i data-lucide="twitter" class="h-5 w-5"></i>
                </a>
                <a href="#" class="w-12 h-12 rounded-full bg-white/80 border border-gray-100 hover:bg-pup-maroon hover:border-pup-maroon hover:text-white flex items-center justify-center text-gray-500 transition-all duration-300 shadow-sm hover:shadow-lg hover:shadow-pup-maroon/30 hover:-translate-y-1.5">
                    <span class="sr-only">Website</span><i data-lucide="globe" class="h-5 w-5"></i>
                </a>
            </div>

            <!-- Copyright Bar -->
            <div class="pt-8 border-t border-gray-200/60 w-full md:w-3/4">
                <p class="text-xs text-gray-400 font-bold uppercase tracking-wider">
                    &copy; <?php echo date("Y"); ?> MediLog System. All rights reserved.
                </p>
            </div>
            
        </div>
    </footer>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>