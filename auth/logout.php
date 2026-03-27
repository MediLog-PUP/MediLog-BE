<?php
session_start();

// Unset all of the session variables
$_SESSION = array();

// Destroy the session completely
session_destroy();

// Redirect back to the main role selection page
header("Location: ../index.php");
exit();
?>