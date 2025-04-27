<?php
// Start session on each page
session_start();

// Database connection settings
$user= $username="nqrvykkmma"; // $username="hqhbadmin";
$pass= $password="x4GrUxnE4B"; // $password="admin@hqhb535251";
$db= $database="nqrvykkmma";   // $database="hqhb";
$host   = '143.110.184.83';
$port = 3306;
// $host   = 'localhost';
// $db     = 'afsm';
// $user   = 'root';
// $pass   = '';
$charset = 'utf8mb4';
$dsn    = "mysql:host=$host;port={$port};dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    exit('Database connection failed.');
}
?>
