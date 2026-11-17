<?php
// Philippine time (UTC+8) for PHP
date_default_timezone_set('Asia/Manila');

// Database Credentials mula sa InfinityFree Control Panel
$host = "sql101.infinityfree.com"; 
$user = "if0_43171125";             
$pass = "Bring1109";    
$db   = "if0_43171125_website3W";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    // Make MySQL use Philippine time too (NOW(), CURRENT_TIMESTAMP, and TIMESTAMP columns)
    try { $pdo->exec("SET time_zone = '+08:00'"); } catch (Exception $e) {}
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>