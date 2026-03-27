<?php
// Database configuration settings
$host = 'localhost';
$dbname = 'medilog_db';
$username = 'root'; // Change this if your database username is different (default for XAMPP/WAMP is 'root')
$password = '';     // Change this if your database has a password (default for XAMPP/WAMP is empty)

try {
    // Create a new PDO instance
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Set the PDO error mode to exception so we can catch errors easily
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set the default fetch mode to associative array for easier data handling
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    // If the connection fails, stop script execution and display an error message
    die("ERROR: Could not connect to the database. " . $e->getMessage());
}
?>