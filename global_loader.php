<!-- Global Loading Screen -->
<style>
    /* CSS for smooth fade out */
    #pageLoader { transition: opacity 0.4s ease-out, visibility 0.4s ease-out; }
    #pageLoader.hidden-loader { opacity: 0; visibility: hidden; }
</style>

<!-- Skeleton Loader Overlay -->
<div id="pageLoader" class="fixed inset-0 z-[9999] flex bg-gray-50 overflow-hidden pointer-events-none">
    
    <!-- Skeleton Sidebar (Desktop only to match app layout) -->
    <div class="hidden md:flex flex-col w-64 bg-gray-900 flex-shrink-0 animate-pulse border-r border-gray-800">
        <!-- Logo Area -->
        <div class="h-[88px] border-b border-gray-800 p-6 flex items-center gap-3">
            <div class="w-10 h-10 bg-red-800/80 rounded-lg"></div>
            <div class="h-6 w-24 bg-red-900/50 rounded"></div>
        </div>
        <!-- Menu Items -->
        <div class="p-4 space-y-4 mt-4">
            <div class="h-12 bg-pup-maroon/40 rounded-xl border border-pup-maroon/20"></div>
            <div class="h-12 bg-gray-800/80 rounded-xl"></div>
            <div class="h-12 bg-gray-800/80 rounded-xl"></div>
            <div class="h-12 bg-gray-800/80 rounded-xl"></div>
            <div class="h-12 bg-gray-800/80 rounded-xl"></div>
        </div>
    </div>

    <!-- Skeleton Main Content Area -->
    <div class="flex-1 flex flex-col h-full w-full bg-slate-50/50">
        
        <!-- Skeleton Header -->
        <div class="h-[73px] bg-white border-b border-gray-200 flex items-center justify-between px-4 sm:px-8 animate-pulse flex-shrink-0 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="md:hidden w-8 h-8 bg-red-100 rounded-lg"></div>
                <div class="h-8 w-32 sm:w-48 bg-red-50 rounded-lg border border-red-100/50"></div>
            </div>
            <div class="flex items-center gap-4">
                <div class="w-9 h-9 bg-red-100 rounded-full"></div>
                <div class="hidden sm:block h-5 w-24 bg-red-50 rounded border border-red-100/50"></div>
                <div class="w-10 h-10 bg-red-200 rounded-full border-2 border-red-100"></div>
            </div>
        </div>

        <!-- Skeleton Body/Content -->
        <div class="flex-1 p-4 sm:p-8 space-y-6 animate-pulse overflow-hidden">
            <!-- Top Stats/Cards Row -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="h-32 bg-white border border-gray-100 rounded-2xl shadow-sm p-6 flex items-center gap-4">
                    <div class="w-12 h-12 bg-blue-100 rounded-xl"></div>
                    <div class="space-y-2 flex-1">
                        <div class="h-4 bg-gray-100 rounded w-2/3"></div>
                        <div class="h-6 bg-blue-50 rounded w-1/3 border border-blue-100/50"></div>
                    </div>
                </div>
                <div class="h-32 bg-white border border-gray-100 rounded-2xl shadow-sm p-6 flex items-center gap-4 hidden sm:flex">
                    <div class="w-12 h-12 bg-green-100 rounded-xl"></div>
                    <div class="space-y-2 flex-1">
                        <div class="h-4 bg-gray-100 rounded w-2/3"></div>
                        <div class="h-6 bg-green-50 rounded w-1/3 border border-green-100/50"></div>
                    </div>
                </div>
                <div class="h-32 bg-white border border-gray-100 rounded-2xl shadow-sm p-6 flex items-center gap-4 hidden lg:flex">
                    <div class="w-12 h-12 bg-yellow-100 rounded-xl"></div>
                    <div class="space-y-2 flex-1">
                        <div class="h-4 bg-gray-100 rounded w-2/3"></div>
                        <div class="h-6 bg-yellow-50 rounded w-1/3 border border-yellow-100/50"></div>
                    </div>
                </div>
                <div class="h-32 bg-white border border-gray-100 rounded-2xl shadow-sm p-6 flex items-center gap-4 hidden lg:flex">
                    <div class="w-12 h-12 bg-red-100 rounded-xl"></div>
                    <div class="space-y-2 flex-1">
                        <div class="h-4 bg-gray-100 rounded w-2/3"></div>
                        <div class="h-6 bg-red-50 rounded w-1/3 border border-red-100/50"></div>
                    </div>
                </div>
            </div>

            <!-- Main Panel -->
            <div class="h-64 bg-white border border-gray-100 rounded-2xl shadow-sm p-6 flex flex-col gap-4 mt-6 relative overflow-hidden">
                <div class="h-6 bg-red-100 rounded w-1/4"></div>
                <div class="flex-1 bg-gradient-to-br from-gray-50 to-red-50/30 rounded-xl border border-gray-100"></div>
            </div>
            
            <!-- Secondary Panel (Desktop) -->
            <div class="h-48 bg-white border border-gray-100 rounded-2xl shadow-sm p-6 flex flex-col gap-4 mt-6 hidden md:flex relative overflow-hidden">
                <div class="h-6 bg-red-100 rounded w-1/4"></div>
                <div class="flex-1 bg-gradient-to-br from-gray-50 to-red-50/30 rounded-xl border border-gray-100"></div>
            </div>
        </div>
    </div>
</div>

<script>
    // Centralized function to forcefully restore visibility
    function hideLoaderAndRevert() {
        document.body.classList.remove('page-exit');
        const loader = document.getElementById('pageLoader');
        if (loader) {
            loader.classList.add('hidden-loader');
        }
    }

    // Hide the loader gracefully when the page fully loads, but wait an extra 800ms
    // to give the user time to see the colored skeleton loading effect.
    window.addEventListener('load', () => {
        setTimeout(hideLoaderAndRevert, 800);
    });

    // --- BULLETPROOF BROWSER BACK BUTTON FIX ---
    // Fire unconditionally on pageshow to catch all back-button navigations
    window.addEventListener('pageshow', (e) => {
        if(e.persisted) {
            hideLoaderAndRevert(); // Instant revert if pulled from bfcache
        } else {
            setTimeout(hideLoaderAndRevert, 800);
        }
    });

    // Show the loader and trigger smooth transition when clicking navigation links
    document.addEventListener('DOMContentLoaded', () => { 
        document.querySelectorAll('a[href]:not([href^="#"]):not([target="_blank"]):not([onclick])').forEach(link => { 
            link.addEventListener('click', e => { 
                const href = link.getAttribute('href'); 
                if (!href || href === "javascript:void(0);") return; 
                e.preventDefault(); 
                
                // Show loader immediately
                const loader = document.getElementById('pageLoader');
                if (loader) {
                    loader.classList.remove('hidden-loader');
                }

                // Apply page exit animation
                document.body.classList.add('page-exit'); 
                
                // Delay actual navigation to allow animations to play (Increased to 500ms)
                setTimeout(() => {
                    window.location.href = href;
                }, 500); 

                // FAILSAFE: Revert the DOM state after 2 seconds in case navigation hangs.
                setTimeout(hideLoaderAndRevert, 2000);
            }); 
        }); 
    });
</script>