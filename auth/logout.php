<?php
// Suppress any background PHP notices/warnings from flashing on the screen
error_reporting(0); 

session_start();

// Rigorously wipe all session data before destroying
$_SESSION = array();
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out... - MediLog</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { pup: { maroon: '#880000', gold: '#F1B500' } } } } }
    </script>
</head>
<body class="bg-gray-50 flex h-screen items-center justify-center overflow-hidden">
    
    <!-- We inject the global skeleton loader directly here to show it immediately -->
    <!-- Added '@' to suppress any include warnings just in case -->
    <?php @include 'global_loader.php'; ?>

    <script>
        // Force the skeleton loader to be visible instantly
        const loader = document.getElementById('pageLoader');
        if (loader) {
            loader.classList.remove('hidden-loader');
            loader.style.opacity = '1';
            loader.style.visibility = 'visible';
        }

        // Redirect smoothly back to the main landing page
        setTimeout(() => {
            window.location.href = '../index.php';
        }, 800);
    </script>
</body>
</html>