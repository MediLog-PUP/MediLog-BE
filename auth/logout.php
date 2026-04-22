<?php
error_reporting(0); 

session_start();

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
    
    <?php @include 'global_loader.php'; ?>

    <script>
        const loader = document.getElementById('pageLoader');
        if (loader) {
            loader.classList.remove('hidden-loader');
            loader.style.opacity = '1';
            loader.style.visibility = 'visible';
        }

        setTimeout(() => {
            window.location.href = '../index.php';
        }, 800);
    </script>
</body>
</html>