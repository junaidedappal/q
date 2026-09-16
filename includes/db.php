<?php
// Turn on error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Database credentials
// $servername = "127.0.0.1";
// $username = "u872975414_webportal"; 
// $password = "Q/T3vv/rmc5o"; 
// $dbname = "u872975414_webportal"; 
// Database credentials
$servername = "127.0.0.1";
$username = "root"; 
$password = ""; 
$dbname = "quidei"; 


// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>