<?php
// mysql_config.php: Load DB config from config.ini based on environment
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Override ENV if needed
if (!defined('ENV')) {
    $hostHeader = $_SERVER['HTTP_HOST'] ?? '';
    $env = (strpos($hostHeader, 'localhost') !== false ||
            strpos($hostHeader, '127.0.0.1') !== false)
           ? 'local' : 'production';
    define('ENV', $env);
}

// Parse config.ini
$config = parse_ini_file(__DIR__ . '/config.ini', true);
if (!$config || !isset($config[ENV])) {
    die("Configuration for environment '" . ENV . "' not found.");
}
$db = $config[ENV];

// Connect using mysqli
$mysqli = new mysqli(
    $db['host'],
    $db['user'],
    $db['pass'],
    $db['dbname'],
    (int)$db['port']
);
if ($mysqli->connect_errno) {
    die("MySQL Connection Error ({$mysqli->connect_errno}): {$mysqli->connect_error}");
}
?>