<?php
// mysql_config.php: Database connection and session start
if (!isset($_SESSION)) session_start();
//
$username="nqrvykkmma"; // $username="hqhbadmin";
$password="x4GrUxnE4B"; // $password="admin@hqhb535251";
$database="nqrvykkmma"; // $database="hqhb";
$mysqli = new mysqli("143.110.184.83", $username, $password, $database);
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