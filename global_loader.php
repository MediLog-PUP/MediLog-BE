<!-- Global Loading Screen -->
<style>
    /* CSS for smooth fade out */
    #pageLoader { transition: opacity 0.3s ease-out, visibility 0.3s ease-out; }
    #pageLoader.hidden-loader { opacity: 0; visibility: hidden; }
</style>

<div id="pageLoader" class="fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-gray-50">
    <div class="relative flex items-center justify-center h-24 w-24">
        <div class="absolute inset-0 rounded-full border-t-4 border-pup-maroon animate-spin"></div>
        <div class="bg-white p-3 rounded-full shadow-sm">
            <i data-lucide="clipboard-heart" class="h-8 w-8 text-pup-maroon animate-pulse"></i>
        </div>
    </div>
    <p class="mt-4 text-sm font-bold text-gray-500 tracking-widest animate-pulse uppercase">Loading</p>
</div>

<script>
    // Hide the loader gracefully when the page fully loads
    window.addEventListener('load', () => {
        const loader = document.getElementById('pageLoader');
        if (loader) {
            loader.classList.add('hidden-loader');
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
                
                // Delay actual navigation to allow animations to play
                setTimeout(() => window.location.href = href, 250); 
            }); 
        }); 
    });
</script>