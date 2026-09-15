<?php
# Connect The Database Via PDO(PHP Data Object)

use FFI\Exception;

$host = 'localhost';  
$port = '8889';
$db = 'skillconnect';
$user = 'root';
$pass = 'root'; 
$charset = 'utf8mb4'; 

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Return rows as associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,
];


try { //Exception handling
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    error_log("Database connection failed: ".$e->getMessage());
   //$e->getMessage(): extracts the plain-text reason why the query failed.
   exit("A database connection error occured. Please try again later.");
}


?>