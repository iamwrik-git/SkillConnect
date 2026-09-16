<?php
// Connect The Database Via PDO(PHP Data Object)

$host = 'localhost';  
$port = '8889'; // MAMP default. Change to 3306 for XAMPP/Production if needed.
$db = 'skillconnect';
$user = 'root';
$pass = 'root'; 
$charset = 'utf8mb4'; 

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,      // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Return rows as associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,            // Use real prepared statements
];

try { 
    // Exception handling
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // $e->getMessage() extracts the plain-text reason why the connection failed.
    error_log("Database connection failed: " . $e->getMessage());
    
    exit("A database connection error occurred. Please try again later.");
}
