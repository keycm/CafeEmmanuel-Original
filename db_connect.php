<?php
// db_connect.php
// FIXED: Using LOCALHOST credentials and unified database name.
$host = "localhost";
$user = "root";
$password = "";
$database = "u763865560_EmmanuelCafeDB";
 
$conn = new mysqli($host, $user, $password, $database);
 
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
