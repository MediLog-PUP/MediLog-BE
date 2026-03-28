<?php
session_start();
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
    
    <!-- We inject the global loader directly here to show it immediately -->
    <?php include '../global_loader.php'; ?>

    <script>
        // Force the loader to be visible instantly
        const loader = document.getElementById('pageLoader');
        if (loader) {
            loader.classList.remove('hidden-loader');
            loader.style.opacity = '1';
            loader.style.visibility = 'visible';
            loader.querySelector('p').innerText = "SIGNING OUT...";
        }

        // Redirect smoothly back to the student login page after 1.2 seconds
        setTimeout(() => {
            window.location.href = '../index.php';
        }, 1200);
    </script>
</body>
</html>