<?php
// mysql_config.php: Database connection and session start
if (!isset($_SESSION)) session_start();

$host     = 'localhost';    // or Cloudways: 'localhost'
$user     = 'root';         // your MySQL user
$pass     = '';             // your MySQL password
$db       = 'your_database';
$port     = 3306;           // default MySQL port

$mysqli = new mysqli($host, $user, $pass, $db, $port);

if ($mysqli->connect_errno) {
    die(
      'MySQL Connection Error (' 
      . $mysqli->connect_errno 
      . '): ' 
      . $mysqli->connect_error
    );
}
$mysqli->select_db($database) or die("Unable to select database");
if ($mysqli -> connect_errno) {
    echo "Failed to connect to MySQL: " . $mysqli -> connect_error;
    exit();
} else {
    print_r($mysqli, "mysqli");
    // $sql = "SELECT * FROM date_mapping"; // ORDER BY Lastname";
    // $result = $mysqli -> query($sql);
    // // Fetch all
    // echo "<pre>";
    // // print_r($result -> fetch_all(MYSQLI_ASSOC));
    // echo "</pre>";
};
?>