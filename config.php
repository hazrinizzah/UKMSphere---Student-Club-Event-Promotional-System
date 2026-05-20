<?php
// Database configuration
$servername = "lrgs.ftsm.ukm.my";
$username = "username";
$password = "password";
$dbname = "dbname";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
?>